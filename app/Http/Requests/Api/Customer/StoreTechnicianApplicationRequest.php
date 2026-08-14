<?php

namespace App\Http\Requests\Api\Customer;

use App\Enums\MediaType;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicianApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::international($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        $image = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        return [
            'full_name' => ['required', 'string', 'min:5', 'max:120', 'regex:/^\S+(\s+\S+){2,}$/u'],
            'phone' => ['required', 'string', 'regex:/^964\d{10}$/'],
            'governorate_id' => ['required', 'integer', Rule::exists('governorates', 'id')->where('is_active', true)],
            'district_id' => [
                'required', 'integer',
                Rule::exists('districts', 'id')
                    ->where('is_active', true)
                    ->where('governorate_id', $this->input('governorate_id')),
            ],
            'specialization_ids' => ['required', 'array', 'min:1'],
            'specialization_ids.*' => [Rule::exists('specializations', 'id')->where('is_active', true)],

            MediaType::PERSONAL->value => $image,
            MediaType::ID_FRONT->value => $image,
            MediaType::ID_BACK->value => $image,
            MediaType::RESIDENCE_FRONT->value => $image,
            MediaType::RESIDENCE_BACK->value => $image,

            'work_samples' => ['sometimes', 'array', 'max:'.MediaType::WORK_SAMPLE_LIMIT],
            'work_samples.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        $documents = collect(MediaType::singleFileTypes())
            ->mapWithKeys(fn (MediaType $type) => [
                "{$type->value}.required" => "{$type->label()} مطلوبة",
                "{$type->value}.image" => "{$type->label()} يجب أن تكون صورة",
                "{$type->value}.max" => "{$type->label()} يجب ألا تتجاوز 4 ميغابايت",
            ])
            ->all();

        return $documents + [
            'full_name.required' => 'الاسم الثلاثي مطلوب',
            'full_name.regex' => 'اكتب الاسم الثلاثي كاملاً',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.regex' => 'رقم الهاتف غير صحيح',
            'governorate_id.required' => 'المحافظة مطلوبة',
            'governorate_id.exists' => 'المحافظة المختارة غير متاحة',
            'district_id.required' => 'القضاء مطلوب',
            'district_id.exists' => 'اختر قضاءً يتبع المحافظة المختارة',
            'specialization_ids.required' => 'اختر اختصاصاً واحداً على الأقل',
            'specialization_ids.*.exists' => 'أحد الاختصاصات المختارة غير متاح',
            'work_samples.max' => 'الحد الأقصى '.MediaType::WORK_SAMPLE_LIMIT.' نماذج أعمال',
            'work_samples.*.image' => 'نماذج الأعمال يجب أن تكون صوراً',
            'work_samples.*.max' => 'حجم صورة نموذج العمل يجب ألا يتجاوز 4 ميغابايت',
        ];
    }
}
