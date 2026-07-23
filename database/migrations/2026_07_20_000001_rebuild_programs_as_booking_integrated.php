<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('program_beneficiary_costs');
        Schema::dropIfExists('program_beneficiaries');
        Schema::dropIfExists('program_room_types');
        Schema::dropIfExists('programs');

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('program_type')->default('camp');
            $table->string('counterparty')->nullable();
            $table->string('employer')->nullable();
            $table->string('contractor')->nullable();
            $table->unsignedSmallInteger('guest_count');
            $table->unsignedSmallInteger('rooms_allocated');
            $table->string('payment_type')->default('payment');
            $table->json('payment_documents')->nullable();
            $table->unsignedBigInteger('base_price')->default(0);
            $table->unsignedBigInteger('services_subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('program_beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('accommodation_id')->nullable();
            $table->string('name');
            $table->string('beneficiary_code')->unique();
            $table->string('national_or_economic_id', 20);
            $table->string('mobile', 15);
            $table->timestamps();
        });

        Schema::create('program_beneficiary_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_beneficiary_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('debt_amount')->default(0);
            $table->text('description')->nullable();
            $table->json('documents')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_beneficiary_costs');
        Schema::dropIfExists('program_beneficiaries');
        Schema::dropIfExists('programs');
    }
};
