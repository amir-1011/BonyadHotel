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
        Schema::create('room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('bed_type')->nullable();
            $table->unsignedTinyInteger('capacity')->default(2);
            $table->decimal('size_sqm', 6, 1)->nullable();
            $table->boolean('smoking')->default(false);
            $table->boolean('has_private_bathroom')->default(true);
            $table->json('images')->nullable();
            $table->json('amenities')->nullable();
            $table->unsignedSmallInteger('room_count')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_types');
    }
};
