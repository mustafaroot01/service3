<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SyncRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.present' => 'قائمة الصلاحيات مطلوبة',
        ];
    }
}
