<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->foreignId('service_catalog_id')->nullable()->after('booking_id')->constrained('service_catalogs')->nullOnDelete();
            $table->unsignedTinyInteger('discount_percentage')->default(0)->after('unit_price');
            $table->unsignedInteger('discount_amount')->default(0)->after('discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_catalog_id');
            $table->dropColumn(['discount_percentage', 'discount_amount']);
        });
    }
};
