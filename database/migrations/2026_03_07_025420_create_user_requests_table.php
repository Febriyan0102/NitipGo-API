<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('password');
            $table->enum('requested_role',['admin','customer'])->default('customer');
            $table->enum('status_requested',['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();

            // Action after request
            $table->text('rejection_reason')->nullable();
            $table->text('rejection_solution')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_requests');
    }
};
