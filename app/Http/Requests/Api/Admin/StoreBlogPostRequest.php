<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'content' => ['required', 'string'],
            'published_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'محتوى المقال مطلوب',
            'image.image' => 'الملف المرفوع يجب أن يكون صورة',
            'published_at.date' => 'تاريخ النشر غير صحيح',
        ];
    }
}
