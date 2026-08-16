<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An account is now created only after its code is verified, so it is always
 * verified and always active — the "pending" status and the phone_verified_at
 * column both lose their meaning. Any rows still carrying "pending" are
 * abandoned signups from the old flow; they never verified and hold no orders,
 * so they are removed before the column goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('status', 'pending')->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
        });
    }
};
