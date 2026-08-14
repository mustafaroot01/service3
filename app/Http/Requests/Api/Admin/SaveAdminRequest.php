<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\AdminStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function adminId(): ?int
    {
        $admin = $this->route('admin');

        return $admin ? (int) (is_object($admin) ? $admin->getKey() : $admin) : null;
    }

    public function rules(): array
    {
        $isCreate = $this->adminId() === null;

        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('admins', 'email')->ignore($this->adminId()),
            ],
            'password' => [$isCreate ? 'required' : 'nullable', 'string', Password::min(8)],
            'role_id' => ['required', 'integer'],
            'status' => [$isCreate ? 'sometimes' : 'required', Rule::enum(AdminStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'name.min' => 'الاسم قصير جداً',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.email' => 'صيغة البريد الإلكتروني غير صحيحة',
            'email.unique' => 'البريد الإلكتروني مستخدم لحساب آخر',
            'password.required' => 'كلمة المرور مطلوبة',
            'password.min' => 'كلمة المرور يجب ألا تقل عن 8 خانات',
            'role_id.required' => 'الدور مطلوب',
            'status.required' => 'الحالة مطلوبة',
        ];
    }
}
