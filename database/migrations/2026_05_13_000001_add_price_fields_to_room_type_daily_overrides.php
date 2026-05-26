<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_type_daily_overrides', function (Blueprint $table) {
            // Custom nightly price — null means use the rate's default price
            $table->unsignedBigInteger('custom_price')->nullable()->after('available_count');
            // Discount percentage 0–100 — applied on top of the custom_price (or default price)
            $table->unsignedTinyInteger('discount_percentage')->nullable()->after('custom_price');
            // Free-form label shown on calendar: e.g. پیک، نوروز، تابستان، آخر هفته...
            $table->string('price_label', 60)->nullable()->after('discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('room_type_daily_overrides', function (Blueprint $table) {
            $table->dropColumn(['custom_price', 'discount_percentage', 'price_label']);
        });
    }
};
