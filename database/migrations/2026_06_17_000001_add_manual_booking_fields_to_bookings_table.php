<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('booking_source')->default('online')->after('tracking_code');
            $table->string('payment_method')->nullable()->after('booking_source');
            $table->string('veteran_type_applied')->nullable()->after('discount_percentage');
            $table->boolean('bill_full_rooms')->default(false)->after('extra_guests_price');
            $table->unsignedInteger('services_subtotal')->default(0)->after('base_price');
            $table->text('notes')->nullable()->after('status');
            $table->string('form_file_path')->nullable()->after('notes');
            $table->foreignId('created_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('guest_contact_name')->nullable()->after('guests');
            $table->string('guest_contact_mobile', 15)->nullable()->after('guest_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'booking_source', 'payment_method', 'veteran_type_applied',
                'bill_full_rooms', 'services_subtotal', 'notes', 'form_file_path',
                'created_by', 'guest_contact_name', 'guest_contact_mobile',
            ]);
        });
    }
};
