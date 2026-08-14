<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreGovernorateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:governorates,name'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المحافظة مطلوب',
            'name.unique' => 'هذه المحافظة موجودة بالفعل',
            'sort_order.integer' => 'ترتيب الفرز يجب أن يكون رقم',
        ];
    }
}
