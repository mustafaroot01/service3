<?php

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingService
{
    private const MASK_CHARACTER = '•';

    private const MASK_LENGTH = 8;

    private const VISIBLE_SUFFIX = 4;

    /** @var array<string, string|null>|null */
    private ?array $memo = null;

    public function get(SettingKey $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key->value] ?? null;

        // false is an unreadable value — treated exactly like an unset one.
        return (! is_string($value) || $value === '') ? ($default ?? $key->default()) : $value;
    }

    public function require(SettingKey $key): string
    {
        $value = $this->get($key);

        if ($value === null) {
            throw new \RuntimeException("الإعداد المطلوب غير مضبوط: {$key->label()}");
        }

        return $value;
    }

    public function set(SettingKey $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        $this->memo = null;
    }

    public function updateMany(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $rawKey => $value) {
                $key = SettingKey::tryFrom($rawKey);

                // Laravel turns a submitted empty field into null, so a row that
                // is present at all is an intent: the mask means "unchanged",
                // anything else — null included — is written through.
                if (! $key || ($value !== null && $this->isMasked($value))) {
                    continue;
                }

                $this->set($key, $value === '' ? null : $value);
            }
        });

        $this->memo = null;
    }

    private function isMasked(string $value): bool
    {
        return str_starts_with($value, str_repeat(self::MASK_CHARACTER, self::MASK_LENGTH));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function presentGrouped(): array
    {
        $stored = $this->all();
        $output = [];

        foreach (SettingKey::grouped() as $group => $keys) {
            $output[] = [
                'group' => $group,
                'group_label' => $keys[0]->groupLabel(),
                'items' => array_map(function (SettingKey $key) use ($stored) {
                    $value = $stored[$key->value] ?? null;
                    $unreadable = $value === false;
                    $isSet = ! $unreadable && $value !== null && $value !== '';

                    return [
                        'key' => $key->value,
                        'label' => $key->label(),
                        'type' => $key->type(),
                        'hint' => $key->hint(),
                        'is_secret' => $key->isSecret(),
                        'is_set' => $isSet,
                        'is_readable' => ! $unreadable,
                        'value' => match (true) {
                            $unreadable => null,
                            $key->isSecret() => $this->mask($value),
                            ! $isSet => $key->default(),
                            default => $value,
                        },
                    ];
                }, $keys),
            ];
        }

        return $output;
    }

    /**
     * Reads every setting in one query. A value that will not decrypt — the
     * signature of a rotated APP_KEY — becomes false instead of an exception,
     * so the panel still opens and the admin can type the key in again.
     *
     * @return array<string, string|false|null>
     */
    private function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        $this->memo = [];

        foreach (Setting::all() as $setting) {
            $name = $setting->getRawOriginal('key');

            try {
                $this->memo[$name] = $setting->value;
            } catch (DecryptException $e) {
                Log::warning('Setting could not be decrypted; APP_KEY may have changed', ['key' => $name]);

                $this->memo[$name] = false;
            }
        }

        return $this->memo;
    }

    private function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return str_repeat(self::MASK_CHARACTER, self::MASK_LENGTH)
            .mb_substr($value, -self::VISIBLE_SUFFIX);
    }
}
