<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'technician_id' => ['required', 'integer', 'exists:technicians,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.required' => 'الفني مطلوب',
            'technician_id.exists' => 'الفني المختار غير موجود',
        ];
    }
}
