<?php

namespace App\Http\Requests\Api\Customer;

use App\Support\VisitWindow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public const MAX_IMAGES = 4;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'time_from' => ['required', 'date_format:H:i'],
            'time_to' => ['required', 'date_format:H:i'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'images' => ['sometimes', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['time_from', 'time_to'])) {
                return;
            }

            if (! VisitWindow::isOpen()) {
                $validator->errors()->add('time_from', 'انتهى وقت استقبال الطلبات لليوم، جرّب بكرة');

                return;
            }

            match (VisitWindow::problemWith($this->input('time_from'), $this->input('time_to'))) {
                'same' => $validator->errors()->add('time_to', 'وقت النهاية يجب أن يختلف عن وقت البداية'),
                'past' => $validator->errors()->add('time_from', 'لا يمكن اختيار وقت مضى'),
                default => null,
            };
        });
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'الخدمة مطلوبة',
            'service_id.exists' => 'الخدمة المختارة غير متاحة',
            'description.required' => 'وصف المشكلة مطلوب',
            'description.min' => 'اكتب وصفاً أوضح للمشكلة',
            'time_from.required' => 'وقت بداية الزيارة مطلوب',
            'time_from.date_format' => 'صيغة الوقت غير صحيحة، مثال: 08:00',
            'time_to.required' => 'وقت نهاية الزيارة مطلوب',
            'time_to.date_format' => 'صيغة الوقت غير صحيحة، مثال: 23:30',
            'latitude.required' => 'حدّد موقعك على الخريطة',
            'longitude.required' => 'حدّد موقعك على الخريطة',
            'images.max' => 'الحد الأقصى '.self::MAX_IMAGES.' صور',
            'images.*.image' => 'الملفات المرفقة يجب أن تكون صوراً',
            'images.*.max' => 'حجم الصورة يجب ألا يتجاوز 4 ميغابايت',
        ];
    }
}
