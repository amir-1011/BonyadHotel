<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_rate_daily_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_rate_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('custom_price')->nullable();
            $table->smallInteger('discount_percentage')->nullable();
            $table->string('price_label', 60)->nullable();
            $table->unique(['room_rate_id', 'date']);
            $table->index('date');
            $table->timestamps();
        });

        Schema::create('room_rate_weekly_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_rate_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->unsignedBigInteger('custom_price')->nullable();
            $table->smallInteger('discount_percentage')->nullable();
            $table->string('price_label', 60)->nullable();
            $table->string('reason', 200)->nullable();
            $table->unique(['room_rate_id', 'weekday']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rate_weekly_price_rules');
        Schema::dropIfExists('room_rate_daily_price_overrides');
    }
};
