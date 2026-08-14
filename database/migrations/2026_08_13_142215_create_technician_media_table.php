<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technician_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('path');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_media');
    }
};
