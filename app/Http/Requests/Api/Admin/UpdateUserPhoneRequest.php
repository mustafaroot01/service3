<?php

namespace App\Http\Requests\Api\Admin;

use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The admin types the familiar 07… form, but the whole system stores and
     * matches on the international 9647… one — so it is normalised here, before
     * the unique check, exactly as registration does it. Nothing downstream
     * ever sees the local shape.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Phone::international($this->input('phone')) ?? $this->input('phone')]);
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'required',
                'string',
                'regex:/^(\+?964|0)?7\d{9}$/',
                Rule::unique('users', 'phone')->ignore($this->route('user')->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.regex' => 'رقم الهاتف غير صحيح، مثال: 07712345678',
            'phone.unique' => 'رقم الهاتف مستخدم لحساب آخر',
        ];
    }
}
