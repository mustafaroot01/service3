<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceServiceImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'اختر صورة',
            'image.image' => 'الملف المرفوع يجب أن يكون صورة',
            'image.mimes' => 'اختر صورة JPG أو PNG أو WEBP',
            'image.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميغابايت',
        ];
    }
}
