<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('services', 'name')
                    ->where('category_id', $this->input('category_id'))
                    ->ignore($this->route('service')),
            ],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'القسم مطلوب',
            'category_id.exists' => 'القسم المختار غير موجود',
            'name.required' => 'اسم الخدمة مطلوب',
            'name.unique' => 'هذه الخدمة موجودة بالفعل ضمن نفس القسم',
        ];
    }
}
