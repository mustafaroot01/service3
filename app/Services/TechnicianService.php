<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Enums\TechnicianStatus;
use App\Models\Technician;
use App\Models\TechnicianMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TechnicianService extends BaseCrudService
{
    protected string $modelClass = Technician::class;

    protected array $searchable = ['name', 'phone'];

    protected array $sortable = ['id', 'name', 'phone', 'status', 'created_at'];

    protected string $defaultSort = 'created_at';

    protected array $filterable = ['status', 'governorate_id', 'district_id', 'source'];

    protected function baseQuery(): Builder
    {
        return $this->query()
            ->with(['governorate', 'district', 'specializations', 'media'])
            ->withCount('orders');
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
        return $model
            ->load(['governorate', 'district', 'specializations', 'media'])
            ->loadCount('orders');
    }

    public function create(array $data): Model
    {
        [$attributes, $specializations, $uploads] = $this->split($data);

        $written = [];

        try {
            $technician = DB::transaction(function () use ($attributes, $specializations, $uploads, &$written) {
                $technician = Technician::create($attributes);
                $technician->specializations()->sync($specializations);
                $this->writeMedia($technician, $uploads, $written);

                return $technician;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($written);
            throw $e;
        }

        return $this->hydrate($technician->refresh());
    }

    public function update(Model $model, array $data): Model
    {
        [$attributes, $specializations, $uploads] = $this->split($data);

        $written = [];
        $replaced = [];

        try {
            DB::transaction(function () use ($model, $attributes, $specializations, $uploads, &$written, &$replaced) {
                $model->fill($attributes)->save();

                if ($specializations !== null) {
                    $model->specializations()->sync($specializations);
                }

                $this->writeMedia($model, $uploads, $written, $replaced);
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($written);
            throw $e;
        }

        Storage::disk('public')->delete($replaced);

        return $this->hydrate($model->refresh());
    }

    public function delete(Model $model): void
    {
        $paths = $model->media()->pluck('path')->all();

        DB::transaction(function () use ($model) {
            $model->specializations()->detach();
            $model->media()->delete();
            $model->delete();
        });

        Storage::disk('public')->delete($paths);
    }

    public function deleteMedia(Technician $technician, TechnicianMedia $media): void
    {
        if ($media->technician_id !== $technician->id) {
            throw ValidationException::withMessages([
                'media' => 'هذا الملف لا يخص هذا الفني',
            ]);
        }

        $path = $media->path;
        $media->delete();

        Storage::disk('public')->delete($path);
    }

    public function changeStatus(Technician $technician, TechnicianStatus $status): Technician
    {
        if ($status === TechnicianStatus::ACTIVE && ! $technician->load('media')->hasCompleteDocuments()) {
            $missing = collect($technician->missingDocuments())
                ->map(fn (MediaType $type) => $type->label())
                ->implode('، ');

            throw ValidationException::withMessages([
                'status' => "لا يمكن تفعيل الفني قبل رفع: {$missing}",
            ]);
        }

        $technician->status = $status;
        $technician->save();

        return $this->hydrate($technician->refresh());
    }

    /**
     * Separates plain columns, the specialization id list and the uploaded files.
     */
    private function split(array $data): array
    {
        $specializations = array_key_exists('specialization_ids', $data)
            ? (array) $data['specialization_ids']
            : null;

        $uploads = [];

        foreach (MediaType::singleFileTypes() as $type) {
            if (($data[$type->value] ?? null) instanceof UploadedFile) {
                $uploads[$type->value] = [$data[$type->value]];
            }
        }

        if (isset($data['work_samples']) && is_array($data['work_samples'])) {
            $files = array_values(array_filter(
                $data['work_samples'],
                fn ($file) => $file instanceof UploadedFile
            ));

            if ($files !== []) {
                $uploads[MediaType::WORK_SAMPLE->value] = $files;
            }
        }

        $attributes = array_intersect_key($data, array_flip([
            'name', 'phone', 'governorate_id', 'district_id',
        ]));

        return [$attributes, $specializations, $uploads];
    }

    /**
     * Uploading a type replaces every existing file of that type.
     *
     * @param  array<string, array<int, UploadedFile>>  $uploads
     * @param  array<int, string>  $written    paths created here, for rollback cleanup
     * @param  array<int, string>  $replaced   paths superseded here, deleted after commit
     */
    private function writeMedia(Technician $technician, array $uploads, array &$written, array &$replaced = []): void
    {
        foreach ($uploads as $typeValue => $files) {
            $type = MediaType::from($typeValue);
            $existing = $technician->media()->where('type', $type)->get();

            // A single-slot document replaces whatever is there; work samples
            // accumulate, because the admin adds them a few at a time.
            if ($type->holdsOneFile() && $existing->isNotEmpty()) {
                $replaced = array_merge($replaced, $existing->pluck('path')->all());
                $technician->media()->where('type', $type)->delete();
                $existing = collect();
            }

            $room = $type->limit() - $existing->count();

            if ($room <= 0) {
                throw ValidationException::withMessages([
                    $type === MediaType::WORK_SAMPLE ? 'work_samples' : $type->value =>
                        "بلغت الحد الأقصى ({$type->limit()}) لـ{$type->label()}، احذف واحدة قبل الإضافة",
                ]);
            }

            $offset = $existing->max('sort') ?? -1;

            foreach (array_slice($files, 0, $room) as $index => $file) {
                $path = $file->store("technicians/{$technician->id}", 'public');
                $written[] = $path;

                $technician->media()->create([
                    'type' => $type,
                    'path' => $path,
                    'sort' => $offset + $index + 1,
                ]);
            }
        }
    }
}
