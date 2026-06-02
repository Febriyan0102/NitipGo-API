<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advertisement_payments', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->string('partner_name');
            $table->string('partner_contact');
            $table->unsignedBigInteger('amount');             
            $table->enum('package', ['basic', 'standard', 'premium']);
            $table->unsignedTinyInteger('duration_days');
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');
            $table->text('snap_token')->nullable();
            $table->string('order_id')->nullable();           
            $table->string('payment_type')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('midtrans_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisement_payments');
    }
};
