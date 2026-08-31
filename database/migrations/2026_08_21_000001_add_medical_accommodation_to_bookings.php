<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_medical_accommodation')->default(false)->after('payment_method');
            $table->string('medical_referral_letter_path')->nullable()->after('is_medical_accommodation');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['is_medical_accommodation', 'medical_referral_letter_path']);
        });
    }
};
