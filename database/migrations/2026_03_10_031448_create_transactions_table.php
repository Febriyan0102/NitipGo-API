<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traveler_id')
                ->nullable()
                ->constrained('travelers')
                ->nullOnDelete();
            $table->foreignId('customer_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('trip_id')
                ->constrained('trips')
                ->cascadeOnDelete();
            $table->foreignId('pickup_point_id')
                ->nullable()
                ->constrained('pickups')
                ->nullOnDelete();
            $table->foreignId('collection_point_id')
                ->nullable()
                ->constrained('collections')
                ->nullOnDelete();
            $table->string('sku')->unique();
            $table->enum('order_type',['titip-beli','kirim']);
            $table->string('name');
            $table->date('arrival_date');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('item_price', 12, 2)->nullable();
            $table->text('destination_address')->nullable();
            $table->text('notes')->nullable();
            $table->text('description')->nullable();
            $table->decimal('weight', 8, 2);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('shipping_price', 12, 2)->default(0);
            $table->decimal('price', 12, 2);
            $table->string('image')->nullable();
            //recipient
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();

            // Setelah recipient_phone
            $table->string('payment_proof')->nullable(); // bukti pembayaran
            $table->timestamp('paid_at')->nullable();
            $table->boolean('price_confirmed')->default(false);

            $table->enum('status',['pending','on_progress','on_the_way','cancelled','finished'])->default('pending');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
