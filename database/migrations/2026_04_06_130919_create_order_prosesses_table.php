<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('order_processes');

        Schema::create('order_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->cascadeOnDelete();
            $table->enum('step', [
                'waiting_acceptance',
                'waiting_payment',
                'paid',
                'in_delivery',
                'completed',
                'cancelled',
            ])->default('waiting_acceptance');
            // update final price
            $table->decimal('original_item_price', 12, 2)->nullable();
            $table->decimal('updated_item_price', 12, 2)->nullable();
            $table->decimal('updated_total_price', 12, 2)->nullable();
            $table->string('receipt_photo')->nullable();
            $table->text('price_notes')->nullable();
            //action after update price
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_processes');
    }
};