<?php
// create_help_tickets_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('customer_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->enum('category', ['Order', 'Pembayaran', 'Pengiriman', 'Traveler', 'Umum', 'Lainnya'])->default('Umum');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('help_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained('help_tickets')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('message');
            $table->boolean('is_admin')->default(false);
            $table->string('author_name');
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('question');
            $table->text('answer');
            $table->enum('category', ['Umum', 'Order', 'Pembayaran', 'Pengiriman', 'Akun'])->default('Umum');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_ticket_replies');
        Schema::dropIfExists('help_tickets');
        Schema::dropIfExists('faqs');
    }
};