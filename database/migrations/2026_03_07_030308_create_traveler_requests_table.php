<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traveler_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('traveler_id')
                ->nullable()
                ->constrained('travelers')
                ->nullOnDelete();

            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('phone');
            $table->string('city');
            $table->string('province');
            $table->text('address');
            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
            $table->string('ktp_number');
            $table->string('ktp_photo');
            $table->string('selfie_with_ktp');
            $table->string('pass_photo');
            $table->string('sim_card_photo');
            $table->enum('status_requested',['pending','approved','rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();

            // Action after request
            $table->text('rejection_reason')->nullable();
            $table->text('rejection_solution')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traveler_requests');
    }
};
