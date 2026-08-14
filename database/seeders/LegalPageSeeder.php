<?php

namespace Database\Seeders;

use App\Enums\LegalPageKey;
use App\Models\LegalPage;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LegalPageKey::cases() as $key) {
            LegalPage::firstOrCreate(
                ['key' => $key->value],
                [
                    'title' => $key->label(),
                    'content' => '<p></p>',
                ]
            );
        }
    }
}
