<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relation::enforceMorphMap() switched every polymorphic column from the
     * fully-qualified class name to a short alias. Rows written before that
     * change still hold the old value and would silently stop matching:
     * admins lose their roles and every issued token stops resolving.
     */
    private const TARGETS = [
        'model_has_roles' => 'model_type',
        'model_has_permissions' => 'model_type',
        'personal_access_tokens' => 'tokenable_type',
        'notifications' => 'notifiable_type',
    ];

    private const MAP = [
        'App\Models\Admin' => 'admin',
        'App\Models\User' => 'user',
        'App\Models\Technician' => 'technician',
    ];

    public function up(): void
    {
        $this->rewrite(self::MAP);
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::MAP));
    }

    private function rewrite(array $map): void
    {
        foreach (self::TARGETS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach ($map as $from => $to) {
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};
