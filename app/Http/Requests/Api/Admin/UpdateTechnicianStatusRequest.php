<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\TechnicianStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTechnicianStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TechnicianStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة مطلوبة',
            'status.Illuminate\Validation\Rules\Enum' => 'الحالة المختارة غير صحيحة',
        ];
    }

    public function status(): TechnicianStatus
    {
        return TechnicianStatus::from($this->validated('status'));
    }
}
