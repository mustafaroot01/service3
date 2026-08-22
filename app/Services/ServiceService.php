<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ServiceService extends BaseCrudService
{
    protected string $modelClass = Service::class;

    protected array $searchable = ['name', 'description'];

    protected array $sortable = ['id', 'name', 'is_active', 'sort_order', 'orders_count', 'created_at'];

    protected string $defaultSort = 'sort_order';

    protected array $filterable = ['is_active', 'category_id'];

    protected string $imageDirectory = 'services';

    protected function baseQuery(): Builder
    {
        return $this->query()->with(['category', 'images'])->withCount('orders');
    }

    public function hydrate(Model $model): Model
    {
        return $model->load(['category', 'images'])->loadCount('orders');
    }

    public function create(array $data): Model
    {
        $files = $this->pullFiles($data);
        $service = parent::create($data);

        return $this->addImages($service, $files);
    }

    public function update(Model $model, array $data): Model
    {
        $files = $this->pullFiles($data);
        $service = parent::update($model, $data);

        return $this->addImages($service, $files);
    }

    /**
     * Appends to the gallery, never past MAX_IMAGES. Files already written are
     * removed again if the rows cannot be saved, so a failed add leaves no
     * orphans on disk.
     */
    public function addImages(Service $service, array $files): Service
    {
        if ($files === []) {
            return $this->hydrate($service->refresh());
        }

        $room = Service::MAX_IMAGES - $service->images()->count();

        if (count($files) > $room) {
            throw ValidationException::withMessages([
                'images' => $room > 0
                    ? "يمكن إضافة {$room} صورة فقط — الحد الأقصى ".Service::MAX_IMAGES.' صور للخدمة'
                    : 'بلغت الحد الأقصى ('.Service::MAX_IMAGES.') لصور الخدمة، احذف واحدة قبل الإضافة',
            ]);
        }

        $sort = (int) ($service->images()->max('sort') ?? -1);
        $written = [];

        try {
            DB::transaction(function () use ($service, $files, &$sort, &$written) {
                foreach ($files as $file) {
                    $path = $file->store($this->imageDirectory, 'public');
                    $written[] = $path;

                    $service->images()->create(['path' => $path, 'sort' => ++$sort]);
                }
            });
        } catch (Throwable $e) {
            foreach ($written as $path) {
                $this->deleteImage($path);
            }

            throw $e;
        }

        return $this->hydrate($service->refresh());
    }

    /** Swaps the file in place so the image keeps its position in the gallery. */
    public function replaceImage(Service $service, ServiceImage $image, UploadedFile $file): Service
    {
        $this->assertOwns($service, $image);

        $old = $image->path;
        $image->update(['path' => $file->store($this->imageDirectory, 'public')]);
        $this->deleteImage($old);

        return $this->hydrate($service->refresh());
    }

    public function removeImage(Service $service, ServiceImage $image): Service
    {
        $this->assertOwns($service, $image);

        $path = $image->path;
        $image->delete();
        $this->deleteImage($path);

        return $this->hydrate($service->refresh());
    }

    /**
     * Orders are permanent records that must keep pointing at a real service, so
     * a service with orders is retired by deactivating it, never deleted. Without
     * this the RESTRICT foreign key would surface as a raw 500. The gallery files
     * go only after the row (and its cascaded image rows) are really gone.
     */
    public function delete(Model $model): void
    {
        $orders = $model->orders()->count();

        if ($orders > 0) {
            abort(422, "لا يمكن حذف خدمة مرتبطة بـ{$orders} طلب. عطّلها بدل الحذف.");
        }

        $paths = $model->images()->pluck('path')->all();

        parent::delete($model);

        foreach ($paths as $path) {
            $this->deleteImage($path);
        }
    }

    private function pullFiles(array &$data): array
    {
        $files = $data['images'] ?? [];
        unset($data['images']);

        return array_values(array_filter((array) $files, fn ($file) => $file instanceof UploadedFile));
    }

    private function assertOwns(Service $service, ServiceImage $image): void
    {
        abort_if($image->service_id !== $service->id, 404);
    }
}
