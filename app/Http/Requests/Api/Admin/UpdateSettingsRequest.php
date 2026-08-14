<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\SettingKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.key' => ['required', 'string', Rule::enum(SettingKey::class)],
            'settings.*.value' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required' => 'لا توجد إعدادات للحفظ',
            'settings.*.key.required' => 'مفتاح الإعداد مطلوب',
            'settings.*.key.Illuminate\Validation\Rules\Enum' => 'مفتاح إعداد غير معروف',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function pairs(): array
    {
        return collect($this->validated('settings'))
            ->mapWithKeys(fn (array $row) => [$row['key'] => $row['value'] ?? null])
            ->all();
    }
}
