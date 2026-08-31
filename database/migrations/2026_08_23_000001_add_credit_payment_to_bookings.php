<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('is_credit')->default(false)->after('medical_referral_letter_path');
            $table->string('credit_letter_path')->nullable()->after('is_credit');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['is_credit', 'credit_letter_path']);
        });
    }
};
