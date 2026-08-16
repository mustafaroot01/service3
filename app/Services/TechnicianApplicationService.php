<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\MediaType;
use App\Enums\SettingKey;
use App\Enums\TechnicianSource;
use App\Enums\TechnicianStatus;
use App\Models\Technician;
use App\Models\TechnicianApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TechnicianApplicationService extends BaseCrudService
{
    /**
     * Applicants upload identity and residence cards. They stay on a private
     * disk the web server never exposes, reached only through a short-lived
     * signed link (App\Support\Media::secureUrl).
     */
    private const DISK = 'local';

    protected string $modelClass = TechnicianApplication::class;

    protected array $searchable = ['full_name', 'phone'];

    protected array $sortable = ['id', 'full_name', 'phone', 'status', 'created_at'];

    protected string $defaultSort = 'created_at';

    protected array $filterable = ['status', 'governorate_id', 'district_id'];

    public function __construct(private readonly SettingService $settings)
    {
        parent::__construct();
    }

    protected function baseQuery(): Builder
    {
        return $this->query()->with(['governorate', 'district', 'specializations']);
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('specialization_id')) {
            $query->whereHas(
                'specializations',
                fn (Builder $q) => $q->whereKey($request->input('specialization_id'))
            );
        }
    }

    public function hydrate(Model $model): Model
    {
        return $model->load(['governorate', 'district', 'specializations', 'media', 'reviewer']);
    }

    public function isOpen(): bool
    {
        return $this->settings->get(SettingKey::TECHNICIAN_APPLICATION_OPEN, '1') === '1';
    }

    public function pendingCount(): int
    {
        return TechnicianApplication::open()->count();
    }

    /**
     * Public submission. The phone is the only gate: one application per number,
     * whatever its status, and never a number that is already a technician.
     */
    public function submit(array $data): TechnicianApplication
    {
        if (! $this->isOpen()) {
            throw ValidationException::withMessages([
                'form' => 'التسجيل كفني مغلق حالياً',
            ]);
        }

        $phone = $data['phone'];

        if (TechnicianApplication::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'تم التسجيل مسبقاً بهذا الرقم',
            ]);
        }

        if (Technician::where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'هذا الرقم مسجّل كفني بالفعل',
            ]);
        }

        $written = [];

        try {
            $application = DB::transaction(function () use ($data, &$written) {
                $application = TechnicianApplication::create([
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'governorate_id' => $data['governorate_id'],
                    'district_id' => $data['district_id'],
                ]);

                $application->specializations()->sync($data['specialization_ids']);
                $this->writeMedia($application, $data, $written);

                return $application;
            });
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($written);
            throw $e;
        }

        return $this->hydrate($application->refresh());
    }

    public function changeStatus(TechnicianApplication $application, ApplicationStatus $status, ?string $note = null): TechnicianApplication
    {
        if ($application->status === $status) {
            throw ValidationException::withMessages([
                'status' => "الاستمارة في حالة ({$status->label()}) بالفعل",
            ]);
        }

        if (! $application->status->canMoveTo($status)) {
            throw ValidationException::withMessages([
                'status' => "لا يمكن الانتقال من ({$application->status->label()}) إلى ({$status->label()})",
            ]);
        }

        if ($status === ApplicationStatus::REJECTED && ($note === null || trim($note) === '')) {
            throw ValidationException::withMessages([
                'note' => 'سبب الرفض مطلوب',
            ]);
        }

        $application->forceFill([
            'status' => $status,
            'note' => $note,
            'reviewed_by' => Auth::guard('admin')->id(),
            'reviewed_at' => now(),
        ])->save();

        return $this->hydrate($application->refresh());
    }

    public function delete(Model $model): void
    {
        $paths = $model->media()->pluck('path')->all();

        DB::transaction(function () use ($model) {
            $model->specializations()->detach();
            $model->media()->delete();
            $model->delete();
        });

        Storage::disk(self::DISK)->delete($paths);
    }

    /**
     * Turns the application into a technician and removes it: the technician's
     * record is the whole point, and `technicians.source` is what remembers he
     * arrived through the form. The files move rather than being re-uploaded.
     */
    public function accept(TechnicianApplication $application): Technician
    {
        if (Technician::where('phone', $application->phone)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'هذا الرقم مسجّل كفني بالفعل، لا يمكن قبول الاستمارة',
            ]);
        }

        $application->loadMissing(['media', 'specializations']);
        $moved = [];
        $technician = null;

        try {
            DB::transaction(function () use ($application, &$moved, &$technician) {
                $technician = Technician::create([
                    'name' => $application->full_name,
                    'phone' => $application->phone,
                    'governorate_id' => $application->governorate_id,
                    'district_id' => $application->district_id,
                    'status' => TechnicianStatus::PENDING,
                    'source' => TechnicianSource::APPLICATION,
                ]);

                $technician->specializations()->sync($application->specializations->pluck('id')->all());

                foreach ($application->media as $media) {
                    $target = "technicians/{$technician->id}/".basename($media->path);

                    Storage::disk(self::DISK)->move($media->path, $target);
                    $moved[] = ['from' => $media->path, 'to' => $target];

                    $technician->media()->create([
                        'type' => $media->type,
                        'path' => $target,
                        'sort' => $media->sort,
                    ]);
                }

                // The rows go; the files stay, now owned by the technician.
                $application->specializations()->detach();
                $application->media()->delete();
                $application->delete();
            });
        } catch (Throwable $e) {
            // The files were moved outside the database's reach, so a rollback
            // has to put them back by hand or the application loses its images.
            foreach ($moved as $move) {
                Storage::disk(self::DISK)->move($move['to'], $move['from']);
            }

            throw $e;
        }

        return $technician->load(['governorate', 'district', 'specializations', 'media']);
    }

    /**
     * @param  array<int, string>  $written  paths created here, for rollback cleanup
     */
    private function writeMedia(TechnicianApplication $application, array $data, array &$written): void
    {
        foreach (MediaType::singleFileTypes() as $type) {
            $file = $data[$type->value] ?? null;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store("applications/{$application->id}", self::DISK);
            $written[] = $path;

            $application->media()->create(['type' => $type, 'path' => $path, 'sort' => 0]);
        }

        $samples = array_values(array_filter(
            $data['work_samples'] ?? [],
            fn ($file) => $file instanceof UploadedFile
        ));

        foreach (array_slice($samples, 0, MediaType::WORK_SAMPLE_LIMIT) as $index => $file) {
            $path = $file->store("applications/{$application->id}", self::DISK);
            $written[] = $path;

            $application->media()->create([
                'type' => MediaType::WORK_SAMPLE,
                'path' => $path,
                'sort' => $index,
            ]);
        }
    }
}
