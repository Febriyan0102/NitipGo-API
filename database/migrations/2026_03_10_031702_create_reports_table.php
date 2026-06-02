<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('traveler_id')
                ->nullable()
                ->constrained('travelers')
                ->nullOnDelete();

            $table->enum('reporter_role',['traveler','customer']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('dispute_priority',['low','medium','high'])->default('medium');
            $table->enum('dispute_status',['open','under_review','resolved'])->default('open');
            $table->text('traveler_note')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
