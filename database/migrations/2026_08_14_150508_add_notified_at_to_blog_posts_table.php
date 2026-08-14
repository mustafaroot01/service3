<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the moment a post's announcement went out. A push cannot be recalled,
 * so this is what stops every later edit from notifying everyone again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable()->after('is_active');
        });

        // Posts that already exist were never announced and must not be now.
        DB::table('blog_posts')->update(['notified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
