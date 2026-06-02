<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traveler_id')
                ->constrained('travelers')
                ->cascadeOnDelete();
            $table->enum('payout_type',['bank','e_wallet'])->default('bank');
            $table->string('provider', 20); // bank/e-wallet code: bca, bni, bri, mandiri, cimb, permata, ovo, dana, gopay, etc.
            $table->string('account_name');
            $table->string('account_number',50);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
