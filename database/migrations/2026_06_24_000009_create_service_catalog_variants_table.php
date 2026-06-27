<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_catalog_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_catalog_id')->constrained('service_catalogs')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['service_catalog_id', 'key'], 'service_catalog_variant_key_unique');
        });

        Schema::table('booking_services', function (Blueprint $table) {
            $table->foreignId('service_catalog_variant_id')
                ->nullable()
                ->after('service_catalog_id')
                ->constrained('service_catalog_variants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_catalog_variant_id');
        });

        Schema::dropIfExists('service_catalog_variants');
    }
};
