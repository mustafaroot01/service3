<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_application_specialization', function (Blueprint $table) {
            $table->id();
            // Shortened from technician_application_id: the generated foreign key
            // name would otherwise pass MySQL's 64-character identifier limit.
            $table->foreignId('application_id')->constrained('technician_applications')->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();

            $table->unique(['application_id', 'specialization_id'], 'application_specialization_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_application_specialization');
    }
};
