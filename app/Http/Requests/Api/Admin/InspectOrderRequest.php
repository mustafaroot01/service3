<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InspectOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inspection_note' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inspection_note.required' => 'ملاحظة الكشف مطلوبة',
            'inspection_note.min' => 'ملاحظة الكشف قصيرة جداً',
        ];
    }
}
