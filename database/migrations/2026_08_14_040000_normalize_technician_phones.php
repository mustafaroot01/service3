<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Technician phones were stored exactly as the admin typed them while every
 * other phone in the system is normalised to 9647…. That let the same person
 * be entered twice past the unique rule, and made matching a technician to an
 * application by phone silently fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('technicians')->select('id', 'phone')->get() as $technician) {
            $normalized = Phone::international($technician->phone);

            if ($normalized !== null && $normalized !== $technician->phone) {
                DB::table('technicians')->where('id', $technician->id)->update(['phone' => $normalized]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('technicians')->select('id', 'phone')->get() as $technician) {
            $digits = preg_replace('/\D+/', '', (string) $technician->phone);

            if (str_starts_with($digits, '964')) {
                DB::table('technicians')
                    ->where('id', $technician->id)
                    ->update(['phone' => '0'.substr($digits, 3)]);
            }
        }
    }
};
