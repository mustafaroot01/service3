<?php

namespace Database\Seeders;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SettingKey::cases() as $key) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => env($key->envKey())]
            );
        }
    }
}
