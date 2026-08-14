<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer asks to be removed; the account is not touched. Deleting it would
 * cascade to every order he ever placed, so the decision stays with the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('status');
            $table->index('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deletion_requested_at']);
            $table->dropColumn('deletion_requested_at');
        });
    }
};
