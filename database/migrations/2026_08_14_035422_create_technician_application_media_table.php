<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_application_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('technician_applications')->cascadeOnDelete();
            $table->string('type');
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['application_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_application_media');
    }
};
