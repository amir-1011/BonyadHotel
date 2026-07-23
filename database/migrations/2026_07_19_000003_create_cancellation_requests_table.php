<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('pending'); // pending, approved, rejected

            $table->foreignId('cancellation_reason_id')->nullable()->constrained('cancellation_reasons')->nullOnDelete();
            $table->string('reason_text')->nullable();
            $table->text('notes')->nullable();

            $table->string('refund_account_number');
            $table->string('refund_account_holder_name')->nullable();

            $table->integer('days_before_checkin');
            $table->unsignedTinyInteger('refund_percentage');
            $table->unsignedInteger('refund_amount');

            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();

            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_requests');
    }
};
