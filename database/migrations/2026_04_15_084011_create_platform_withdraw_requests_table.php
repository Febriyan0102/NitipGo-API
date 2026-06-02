<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_withdraw_requests', function (Blueprint $table) {
            $table->id();

            // Payout account admin
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_name');

            // Nominal
            $table->decimal('amount',     15, 2);
            $table->decimal('fee',        15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);

            // Status: completed 
            $table->string('withdraw_status')->default('completed');

            // Note & reference
            $table->text('note')->nullable();
            $table->string('reference_no')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_withdraw_requests');
    }
};