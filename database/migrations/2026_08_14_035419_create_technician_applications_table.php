<?php

use App\Enums\ApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technician_applications', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            // Unique: one application per phone, which is what blocks a resubmit.
            $table->string('phone')->unique();
            $table->foreignId('governorate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('district_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default(ApplicationStatus::PENDING->value);
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('governorate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_applications');
    }
};
