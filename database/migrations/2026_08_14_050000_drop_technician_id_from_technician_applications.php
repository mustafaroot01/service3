<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accepting an application now removes it, so it can never hold a technician
 * reference. What remembers the origin is technicians.source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_applications', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
            $table->dropColumn('technician_id');
        });
    }

    public function down(): void
    {
        Schema::table('technician_applications', function (Blueprint $table) {
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
