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
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  
            $table->string('partner_name');
            $table->string('partner_contact');
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('image_path')->nullable();      
            $table->text('link_url');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('duration_days');    
            $table->enum('package', ['basic', 'standard', 'premium']); 
            $table->enum('status', ['pending', 'active', 'expired', 'rejected'])->default('pending');
            $table->integer('slot_index')->nullable();      
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
