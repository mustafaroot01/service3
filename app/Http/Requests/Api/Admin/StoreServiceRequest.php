<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
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
                Rule::unique('services', 'name')->where('category_id', $this->input('category_id')),
            ],
            'images' => ['sometimes', 'array', 'max:'.Service::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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
            'images.max' => 'الحد الأقصى '.Service::MAX_IMAGES.' صور للخدمة',
            'images.*.image' => 'الملف المرفوع يجب أن يكون صورة',
            'images.*.mimes' => 'اختر صورة JPG أو PNG أو WEBP',
            'images.*.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميغابايت',
            'image.image' => 'الملف المرفوع يجب أن يكون صورة',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميغابايت',
        ];
    }
}
