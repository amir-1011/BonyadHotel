<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_type_blocked_dates', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
        });

        Schema::table('room_type_blocked_dates', function (Blueprint $table) {
            $table->dropUnique(['room_type_id', 'date']);
            $table->foreignId('room_id')->nullable()->after('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreign('room_type_id')->references('id')->on('room_types')->cascadeOnDelete();
            $table->unique(['room_type_id', 'room_id', 'date'], 'room_type_blocked_dates_unique');
        });
    }

    public function down(): void
    {
        Schema::table('room_type_blocked_dates', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->dropUnique('room_type_blocked_dates_unique');
            $table->dropConstrainedForeignId('room_id');
        });

        Schema::table('room_type_blocked_dates', function (Blueprint $table) {
            $table->unique(['room_type_id', 'date']);
            $table->foreign('room_type_id')->references('id')->on('room_types')->cascadeOnDelete();
        });
    }
};
