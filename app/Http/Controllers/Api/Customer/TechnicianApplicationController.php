<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\StoreTechnicianApplicationRequest;
use App\Services\TechnicianApplicationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TechnicianApplicationController extends Controller
{
    public function __construct(private readonly TechnicianApplicationService $applications) {}

    /** Lets the app hide the whole entry point while the form is switched off. */
    public function form(): JsonResponse
    {
        return ApiResponse::success([
            'is_open' => $this->applications->isOpen(),
            'required_documents' => collect(MediaType::requiredDocuments())
                ->map(fn (MediaType $type) => ['key' => $type->value, 'label' => $type->label()])
                ->all(),
            'work_samples_key' => 'work_samples',
            'work_samples_limit' => MediaType::WORK_SAMPLE_LIMIT,
            'max_file_mb' => 4,
        ], 'Technician application form retrieved successfully');
    }

    public function store(StoreTechnicianApplicationRequest $request): JsonResponse
    {
        $this->applications->submit($request->validated() + $request->allFiles());

        return ApiResponse::success(null, 'شكراً على إرسال طلبك، سيتم الاتصال بك', 201);
    }
}
