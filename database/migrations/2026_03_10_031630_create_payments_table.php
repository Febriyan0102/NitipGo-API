<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('traveler_id')
                ->nullable()
                ->constrained('travelers')
                ->nullOnDelete();

            // Midtrans fields
            $table->string('snap_token')->nullable();
            $table->string('midtrans_order_id')->unique();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('payment_type')->nullable();      // bank_transfer, gopay, shopeepay, credit_card, qris, etc.
            $table->string('payment_channel')->nullable();   // bca, bni, mandiri, bri, gopay, shopeepay, etc.
            $table->string('va_number')->nullable();         // Virtual Account number from Midtrans

            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);

            $table->enum('payment_status', ['pending', 'paid', 'failed', 'expired', 'refunded', 'cancelled'])->default('pending');
            $table->string('payment_reference')->unique();
            $table->text('reject_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};