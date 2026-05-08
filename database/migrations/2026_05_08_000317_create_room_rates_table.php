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
        Schema::create('room_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('price_per_night');
            $table->boolean('breakfast_included')->default(false);
            $table->unsignedInteger('breakfast_price_per_person')->nullable();
            $table->enum('cancellation_policy', ['free', 'non_refundable'])->default('free');
            $table->enum('payment_type', ['pay_at_hotel', 'prepay_online'])->default('pay_at_hotel');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};
