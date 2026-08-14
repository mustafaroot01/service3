<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'governorate_id' => ['required', 'integer', Rule::exists('governorates', 'id')->where('is_active', true)],
            'district_id' => [
                'required', 'integer',
                Rule::exists('districts', 'id')
                    ->where('governorate_id', $this->input('governorate_id'))
                    ->where('is_active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'name.min' => 'الاسم قصير جداً',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'governorate_id.exists' => 'المحافظة المختارة غير متاحة',
            'district_id.exists' => 'القضاء المختار لا يتبع المحافظة المختارة أو غير متاح',
        ];
    }
}
