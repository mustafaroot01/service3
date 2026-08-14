<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:user'],
            'password' => ['required', 'string', Password::min(8)->max(64), 'confirmed', 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'كلمة السر الحالية مطلوبة',
            'current_password.current_password' => 'كلمة السر الحالية غير صحيحة',
            'password.required' => 'كلمة السر الجديدة مطلوبة',
            'password.min' => 'كلمة السر يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة السر غير مطابق',
            'password.different' => 'كلمة السر الجديدة يجب أن تختلف عن الحالية',
        ];
    }
}
