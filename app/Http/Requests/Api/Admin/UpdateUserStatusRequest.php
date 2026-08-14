<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'الحالة مطلوبة',
            'status.Illuminate\Validation\Rules\Enum' => 'الحالة المختارة غير صحيحة',
        ];
    }

    public function status(): UserStatus
    {
        return UserStatus::from($this->validated('status'));
    }
}
