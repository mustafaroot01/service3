<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie's name is a code identifier (super-admin) referenced by syncRoles and
 * the role middleware. The panel needs a separate Arabic display name that an
 * admin can type and change without breaking those references.
 */
return new class extends Migration
{
    private const SEEDED = [
        'super-admin' => 'مدير عام',
        'manager' => 'مدير',
        'viewer' => 'مطّلع',
    ];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('label')->nullable()->after('name');
        });

        foreach (self::SEEDED as $name => $label) {
            DB::table('roles')->where('name', $name)->update(['label' => $label]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
