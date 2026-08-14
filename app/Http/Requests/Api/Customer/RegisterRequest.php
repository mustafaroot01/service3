<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'phone' => ['required', 'string', 'regex:/^(\+?964|0)?7\d{9}$/'],
            'password' => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
            'governorate_id' => ['required', 'integer', Rule::exists('governorates', 'id')->where('is_active', true)],
            'district_id' => [
                'required', 'integer',
                Rule::exists('districts', 'id')
                    ->where('governorate_id', $this->input('governorate_id'))
                    ->where('is_active', true),
            ],
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'name.min' => 'الاسم قصير جداً',
            'gender.required' => 'الجنس مطلوب',
            'gender.in' => 'الجنس يجب أن يكون ذكر أو أنثى',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.regex' => 'رقم الهاتف غير صحيح، مثال: 07712345678',
            'password.required' => 'كلمة السر مطلوبة',
            'password.min' => 'كلمة السر يجب أن تكون 8 أحرف على الأقل',
            'password.confirmed' => 'تأكيد كلمة السر غير مطابق',
            'governorate_id.required' => 'المحافظة مطلوبة',
            'governorate_id.exists' => 'المحافظة المختارة غير متاحة',
            'district_id.required' => 'القضاء مطلوب',
            'district_id.exists' => 'القضاء المختار لا يتبع المحافظة المختارة أو غير متاح',
            'terms_accepted.accepted' => 'يجب الموافقة على شروط الاستخدام',
        ];
    }
}
