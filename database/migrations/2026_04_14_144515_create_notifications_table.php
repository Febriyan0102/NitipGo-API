<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // Target role
            $table->unsignedBigInteger('user_id')->nullable();      
            $table->unsignedBigInteger('traveler_id')->nullable();  
            $table->string('role')->default('customer');           

            // content
            $table->string('type');           
            $table->string('title');
            $table->text('message');
            $table->string('icon')->nullable();  

            // Link action (optional)
            $table->string('action_url')->nullable();
            $table->string('action_label')->nullable();

            $table->string('notifiable_type')->nullable(); 
            $table->unsignedBigInteger('notifiable_id')->nullable();

            // Status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_read']);
            $table->index(['traveler_id', 'is_read']);
            $table->index(['role', 'is_read']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};