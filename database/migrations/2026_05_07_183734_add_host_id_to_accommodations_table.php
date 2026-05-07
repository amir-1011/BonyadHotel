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
        Schema::table('accommodations', function (Blueprint $table) {
            $table->foreignId('host_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('images')->nullable()->after('image'); // JSON array of image paths
        });
    }

    public function down(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropForeign(['host_id']);
            $table->dropColumn(['host_id', 'images']);
        });
    }
};
