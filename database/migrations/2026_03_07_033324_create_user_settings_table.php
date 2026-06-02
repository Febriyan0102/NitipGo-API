<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('traveler_id')
                ->nullable()
                ->constrained('travelers')
                ->nullOnDelete();
            $table->boolean('notify_email')->default(false);
            $table->boolean('notify_push')->default(false);
            $table->boolean('notify_order')->default(false);
            $table->boolean('notify_payment')->default(false);
            $table->boolean('two_factor_enabled')->default(false);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
