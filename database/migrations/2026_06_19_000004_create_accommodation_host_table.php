<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accommodation_host', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['accommodation_id', 'user_id']);
        });

        DB::table('accommodations')
            ->whereNotNull('host_id')
            ->orderBy('id')
            ->each(function ($accommodation) {
                DB::table('accommodation_host')->insertOrIgnore([
                    'accommodation_id' => $accommodation->id,
                    'user_id'          => $accommodation->host_id,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_host');
    }
};
