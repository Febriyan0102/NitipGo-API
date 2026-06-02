<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traveler_id')
                ->nullable()
                ->constrained('travelers')
                ->nullOnDelete();

            $table->string('code')->unique();
            $table->string('city');
            $table->string('destination');
            $table->dateTime('departure_at');
            $table->dateTime('estimated_arrival_at')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('capacity',8,2); 
            $table->decimal('used_capacity',8,2)->default(0);
            $table->text('description')->nullable();
            $table->enum('status',['draft','active','inactive','on_trip','completed','cancelled', 'expired'])->default('draft');
            $table->unsignedInteger('orders_count')->default(0);
            $table->index('traveler_id');
            $table->index('status');
            $table->index('departure_at');

            //tracking
            $table->boolean('is_tracking')->default(false);
            $table->timestamp('tracking_started_at')->nullable();
            $table->timestamp('tracking_finished_at')->nullable();
            $table->decimal('origin_latitude',10,7)->nullable();
            $table->decimal('origin_longitude',10,7)->nullable();
            $table->decimal('destination_latitude',10,7)->nullable();
            $table->decimal('destination_longitude',10,7)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
