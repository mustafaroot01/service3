<?php

namespace App\Http\Requests\Api\Admin;

use App\Services\RoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => [
                'required', 'string', 'min:2', 'max:60',
                Rule::unique('roles', 'label')
                    ->where('guard_name', RoleService::GUARD)
                    ->ignore($this->route('role')),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.required' => 'اسم الدور مطلوب',
            'label.min' => 'اسم الدور قصير جداً',
            'label.unique' => 'يوجد دور بهذا الاسم',
            'permissions.present' => 'قائمة الصلاحيات مطلوبة',
        ];
    }
}
