<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "pending" meant registered but not yet verified by code. Signup no longer
 * sends a code, so nothing produces that state any more. Accounts still
 * carrying it are moved to the state they would be created with today,
 * otherwise reading them would fail once the enum drops the case.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('status', 'pending')->update([
            'status' => 'active',
            'phone_verified_at' => DB::raw('COALESCE(phone_verified_at, NOW())'),
        ]);
    }

    public function down(): void
    {
        // The original state cannot be told apart afterwards, so nothing is undone.
    }
};
