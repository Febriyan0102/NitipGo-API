<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_trackings', function (Blueprint $table) {
        $table->id();

        $table->foreignId('trip_id')
            ->constrained('trips')
            ->cascadeOnDelete();

        $table->decimal('latitude', 10, 7);
        $table->decimal('longitude', 10, 7);

        $table->decimal('speed', 8, 2)->nullable();
        $table->decimal('heading', 8, 2)->nullable();

        $table->timestamp('recorded_at');
        $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_trackings');
    }
};
