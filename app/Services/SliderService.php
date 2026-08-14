<?php

namespace App\Services;

use App\Models\Slider;

class SliderService extends BaseCrudService
{
    protected string $modelClass = Slider::class;

    protected array $sortable = ['id', 'is_active', 'sort_order', 'created_at'];

    protected string $defaultSort = 'sort_order';

    protected array $filterable = ['is_active'];

    protected array $imageFields = ['image'];

    protected string $imageDirectory = 'sliders';
}
