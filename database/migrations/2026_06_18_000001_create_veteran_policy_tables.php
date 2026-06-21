<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veteran_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->unsignedTinyInteger('accommodation_discount')->default(0);
            $table->unsignedSmallInteger('nights_per_dependent')->default(6);
            $table->unsignedSmallInteger('max_nights_per_period')->default(3);
            $table->unsignedSmallInteger('period_months')->default(6);
            $table->unsignedSmallInteger('weekly_free_sessions')->default(0);
            $table->text('usage_notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('default_price')->default(0);
            $table->boolean('supports_free_sessions')->default(false);
            $table->unsignedTinyInteger('default_discount')->default(0);
            $table->unsignedTinyInteger('min_discount')->nullable();
            $table->unsignedTinyInteger('max_discount')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('veteran_group_service_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('veteran_group_id')->constrained('veteran_groups')->cascadeOnDelete();
            $table->foreignId('service_catalog_id')->constrained('service_catalogs')->cascadeOnDelete();
            $table->unsignedTinyInteger('discount_percentage')->default(0);
            $table->boolean('free_sessions_eligible')->default(false);
            $table->timestamps();

            $table->unique(['veteran_group_id', 'service_catalog_id'], 'veteran_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veteran_group_service_discounts');
        Schema::dropIfExists('service_catalogs');
        Schema::dropIfExists('veteran_groups');
    }
};
