<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'العنوان مطلوب',
            'content.required' => 'المحتوى مطلوب',
        ];
    }
}
