<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_foreign_guest')->default(false)->after('national_id');
            $table->string('passport_number', 32)->nullable()->unique()->after('is_foreign_guest');
            $table->foreignId('country_id')->nullable()->after('passport_number')->constrained()->nullOnDelete();
            $table->foreignId('residence_city_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });

        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->boolean('is_foreign_guest')->default(false)->after('national_id');
            $table->string('passport_number', 32)->nullable()->after('is_foreign_guest');
            $table->foreignId('country_id')->nullable()->after('passport_number')->constrained()->nullOnDelete();
            $table->foreignId('residence_city_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('residence_city_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn(['is_foreign_guest', 'passport_number']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('residence_city_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropColumn(['is_foreign_guest', 'passport_number']);
        });
    }
};
