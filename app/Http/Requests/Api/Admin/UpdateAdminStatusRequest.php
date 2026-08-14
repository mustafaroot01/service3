<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\AdminStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(AdminStatus::class)]];
    }

    public function status(): AdminStatus
    {
        return AdminStatus::from($this->validated('status'));
    }

    public function messages(): array
    {
        return ['status.required' => 'الحالة مطلوبة'];
    }
}
