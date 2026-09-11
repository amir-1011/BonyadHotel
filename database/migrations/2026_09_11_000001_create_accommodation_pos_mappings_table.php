<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accommodation_pos_mappings')) {
            Schema::create('accommodation_pos_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accommodation_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('windows_lan_ip', 45);
                $table->string('pos_lan_ip', 45);
                $table->unsignedSmallInteger('pos_port')->default(1362);
                $table->unsignedSmallInteger('agent_port')->default(8088);
                $table->boolean('is_active')->default(true);
                $table->string('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('booking_payment_records') && ! Schema::hasColumn('booking_payment_records', 'pos_response')) {
            Schema::table('booking_payment_records', function (Blueprint $table) {
                $table->json('pos_response')->nullable()->after('document_paths');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('booking_payment_records') && Schema::hasColumn('booking_payment_records', 'pos_response')) {
            Schema::table('booking_payment_records', function (Blueprint $table) {
                $table->dropColumn('pos_response');
            });
        }

        Schema::dropIfExists('accommodation_pos_mappings');
    }
};
