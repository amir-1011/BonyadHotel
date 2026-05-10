<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تعداد اتاق‌هایی که این رزرو اشغال می‌کند.
     * = ceil(guests / room_type.capacity)
     * مقدار پیش‌فرض ۱ برای رزروهای قدیمی.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('rooms_consumed')->default(1)->after('guests');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('rooms_consumed');
        });
    }
};
