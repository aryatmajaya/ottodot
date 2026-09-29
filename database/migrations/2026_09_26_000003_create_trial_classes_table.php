<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_classes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subject');
            $table->timestamp('starts_at');
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->unsignedTinyInteger('confirmed_count')->default(0);
            $table->timestamps();

            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_classes');
    }
};
