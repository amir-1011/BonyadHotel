<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pos_terminals')) {
            Schema::create('pos_terminals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('province_id')->constrained()->cascadeOnDelete();
                $table->string('terminal_number', 64);
                $table->string('label')->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['province_id', 'terminal_number']);
            });
        }

        if (! Schema::hasTable('booking_payment_records')) {
            Schema::create('booking_payment_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->integer('amount');
                $table->integer('amount_delta')->default(0);
                $table->string('price_adjustment_reason')->nullable();
                $table->string('card_last_four', 4)->nullable();
                $table->string('transaction_tracking')->nullable();
                $table->timestamp('payment_at');
                $table->foreignId('pos_terminal_id')->nullable()->constrained('pos_terminals')->nullOnDelete();
                $table->json('document_paths')->nullable();
                $table->string('context', 64);
                $table->string('action', 64)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['payment_at']);
                $table->index(['pos_terminal_id', 'payment_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payment_records');
        Schema::dropIfExists('pos_terminals');
    }
};
