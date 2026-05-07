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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('mobile', 15)->unique();
            $table->string('national_id', 10)->nullable();
            $table->string('veteran_type')->nullable(); // martyr_family, veteran_25_49, veteran_50_69, veteran_70_plus, freed_prisoner_family
            $table->unsignedTinyInteger('discount_percentage')->default(0);
            $table->timestamp('mobile_verified_at')->nullable();
            $table->timestamp('national_id_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('otp_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('identifier'); // mobile number
            $table->string('token', 6);
            $table->boolean('valid')->default(true);
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('otp_tokens');
        Schema::dropIfExists('sessions');
    }
};
