<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travelers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->unique();
            $table->string('role')->default('traveler');
            $table->string('city');
            $table->string('province');
            $table->text('address');
            $table->date('birth_date');
            $table->enum('gender', ['male', 'female']);
            $table->string('ktp_number')->unique();
            $table->string('ktp_photo');
            $table->string('selfie_with_ktp');
            $table->string('pass_photo');
            $table->string('sim_card_photo');
            $table->string('profile_photo')->nullable();
            $table->enum('status',['active', 'non_active'])->nullable();
            $table->decimal('balance', 15, 2)->default(0); // add balance 
            $table->rememberToken();
            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travelers');
    }
};
