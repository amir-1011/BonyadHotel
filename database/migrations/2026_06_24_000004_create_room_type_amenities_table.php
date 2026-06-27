<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_amenities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $sort = 0;
        $seen = [];

        foreach (config('room_types.amenities', []) as $name) {
            $name = trim((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            DB::table('room_type_amenities')->insert([
                'name'       => $name,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('room_types')->whereNotNull('amenities')->pluck('amenities') as $json) {
            foreach (json_decode($json, true) ?? [] as $name) {
                $name = trim((string) $name);
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;
                DB::table('room_type_amenities')->insert([
                    'name'       => $name,
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('rooms')) {
            foreach (DB::table('rooms')->whereNotNull('amenities')->pluck('amenities') as $json) {
                foreach (json_decode($json, true) ?? [] as $name) {
                    $name = trim((string) $name);
                    if ($name === '' || isset($seen[$name])) {
                        continue;
                    }
                    $seen[$name] = true;
                    DB::table('room_type_amenities')->insert([
                        'name'       => $name,
                        'sort_order' => $sort++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_type_amenities');
    }
};
