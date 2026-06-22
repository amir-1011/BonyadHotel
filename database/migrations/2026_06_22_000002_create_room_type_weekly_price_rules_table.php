<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_weekly_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            // PHP date('N'): 1=Mon … 5=Fri, 6=Sat, 7=Sun
            $table->unsignedTinyInteger('weekday');
            $table->unsignedBigInteger('custom_price')->nullable();
            // Positive = discount, negative = surcharge (e.g. -20 = 20% more expensive)
            $table->smallInteger('discount_percentage')->nullable();
            $table->string('price_label', 60)->nullable();
            $table->string('reason', 200)->nullable();
            $table->unique(['room_type_id', 'weekday']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_weekly_price_rules');
    }
};
