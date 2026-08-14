<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('districts', 'name')->where('governorate_id', $this->input('governorate_id')),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'governorate_id.required' => 'المحافظة مطلوبة',
            'governorate_id.exists' => 'المحافظة المختارة غير موجودة',
            'name.required' => 'اسم القضاء مطلوب',
            'name.unique' => 'هذا القضاء موجود بالفعل ضمن نفس المحافظة',
        ];
    }
}
