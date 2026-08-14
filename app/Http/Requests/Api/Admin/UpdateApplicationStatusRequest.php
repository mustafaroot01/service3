<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ApplicationStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::from($this->validated('status'));
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة مطلوبة',
            'note.max' => 'الملاحظة طويلة جداً',
        ];
    }
}
