<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_boosters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('traveler_id')
                ->constrained('travelers')
                ->cascadeOnDelete();

            $table->foreignId('booster_id')
                ->constrained('boosters')
                ->cascadeOnDelete();

            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);

            // Midtrans fields
            $table->string('snap_token')->nullable();
            $table->string('midtrans_order_id')->unique();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('payment_channel')->nullable();
            $table->string('va_number')->nullable();

            $table->string('payment_reference')->unique();
            $table->text('reject_reason')->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_boosters');
    }
};