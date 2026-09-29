<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('trial_class_id')->constrained()->restrictOnDelete();
            $table->string('status');
            $table->timestamp('seat_claimed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            // One booking record per child/class makes booking submission idempotent.
            $table->unique(['student_id', 'trial_class_id']);
            $table->index(['trial_class_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
