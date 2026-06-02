<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traveler_boosters', function (Blueprint $table) {
            $table->id();                                                                                           

            $table->foreignId('traveler_id')
                ->constrained('travelers')
                ->cascadeOnDelete();

            $table->foreignId('booster_id')
                ->constrained('boosters')
                ->cascadeOnDelete();
            
            $table->foreignId('payment_booster_id')
                ->nullable()
                ->constrained('payment_boosters')
                ->nullOnDelete();

            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->unsignedInteger('orders_gained')->default(0);
            $table->enum('status',['active','expired','suspended'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {                                                                                                           
        Schema::dropIfExists('traveler_boosters');
    }
};
