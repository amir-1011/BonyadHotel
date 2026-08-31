<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('facility_item_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('facility_exchange_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 200);
            $table->foreignId('brand_id')->nullable()->constrained('facility_item_brands')->nullOnDelete();
            $table->foreignId('category_id')->constrained('facility_item_categories');
            $table->string('unit_volume', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('province_id')->constrained('provinces');
            $table->date('expiry_date')->nullable();
            $table->string('image_path')->nullable();
            $table->string('contact_phone', 20);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index('province_id');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_exchange_items');
        Schema::dropIfExists('facility_item_brands');
        Schema::dropIfExists('facility_item_categories');
    }
};
