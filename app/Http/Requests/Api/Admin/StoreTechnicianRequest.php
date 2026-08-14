<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\MediaType;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Stored in one shape (9647…) like every other phone in the system —
     * otherwise the same person passes the unique rule twice, once as
     * 0771… and once as 964771….
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => Phone::international($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        $image = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^964\\d{10}$/', 'unique:technicians,phone'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'district_id' => [
                'required', 'integer',
                Rule::exists('districts', 'id')->where('governorate_id', $this->input('governorate_id')),
            ],
            'specialization_ids' => ['sometimes', 'array'],
            'specialization_ids.*' => ['integer', 'distinct', 'exists:specializations,id'],
            'work_samples' => ['sometimes', 'array', 'max:'.MediaType::WORK_SAMPLE_LIMIT],
            'work_samples.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];

        foreach (MediaType::singleFileTypes() as $type) {
            $rules[$type->value] = $image;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم الفني مطلوب',
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.regex' => 'رقم الهاتف غير صحيح',
            'phone.unique' => 'رقم الهاتف مسجّل لفني آخر',
            'governorate_id.required' => 'المحافظة مطلوبة',
            'governorate_id.exists' => 'المحافظة المختارة غير موجودة',
            'district_id.required' => 'القضاء مطلوب',
            'district_id.exists' => 'القضاء المختار لا يتبع المحافظة المختارة',
            'specialization_ids.*.exists' => 'أحد الاختصاصات المختارة غير موجود',
            'work_samples.max' => 'الحد الأقصى '.MediaType::WORK_SAMPLE_LIMIT.' نماذج أعمال',
            'work_samples.*.image' => 'نماذج الأعمال يجب أن تكون صوراً',
        ];
    }

    public function attributes(): array
    {
        return collect(MediaType::cases())
            ->mapWithKeys(fn (MediaType $type) => [$type->value => $type->label()])
            ->all();
    }
}
