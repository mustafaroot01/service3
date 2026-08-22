<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.Service::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'اختر صورة واحدة على الأقل',
            'images.max' => 'الحد الأقصى '.Service::MAX_IMAGES.' صور للخدمة',
            'images.*.image' => 'الملف المرفوع يجب أن يكون صورة',
            'images.*.mimes' => 'اختر صورة JPG أو PNG أو WEBP',
            'images.*.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميغابايت',
        ];
    }
}
