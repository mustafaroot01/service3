<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A service now carries a small gallery instead of one image. The existing
 * single image becomes the first gallery row so nothing is lost, and the old
 * column goes away so there is exactly one source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (DB::table('services')->whereNotNull('image')->where('image', '<>', '')->orderBy('id')->get() as $service) {
            DB::table('service_images')->insert([
                'service_id' => $service->id,
                'path' => $service->image,
                'sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('image'));
    }

    public function down(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->string('image')->nullable()->after('name'));

        DB::table('service_images')->orderBy('sort')->orderBy('id')->get()
            ->groupBy('service_id')
            ->each(fn ($images, $serviceId) => DB::table('services')
                ->where('id', $serviceId)
                ->update(['image' => $images->first()->path]));

        Schema::dropIfExists('service_images');
    }
};
