<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->boolean('children_under_6_allocate_bed')->default(true)->after('capacity');
            $table->unsignedTinyInteger('children_under_6_discount_percentage')->default(50)->after('children_under_6_allocate_bed');
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn([
                'children_under_6_allocate_bed',
                'children_under_6_discount_percentage',
            ]);
        });
    }
};
