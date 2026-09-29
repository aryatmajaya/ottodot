<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('provider_transaction_id')->unique();
            $table->string('outcome');
            $table->unsignedInteger('amount_cents')->default(0);
            $table->string('failure_code')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->index(['booking_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
