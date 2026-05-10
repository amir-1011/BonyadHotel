<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_daily_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('available_count'); // 0 = host closed for that day
            $table->string('reason', 200)->nullable();
            $table->unique(['room_type_id', 'date']);
            $table->index('date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_daily_overrides');
    }
};
