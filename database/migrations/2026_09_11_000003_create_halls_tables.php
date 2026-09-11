<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hall_types')) {
            Schema::create('hall_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            $sort = 0;
            $seen = [];
            foreach (config('halls.types', []) as $name) {
                $name = trim((string) $name);
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;
                DB::table('hall_types')->insert([
                    'name'       => $name,
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('hall_amenities')) {
            Schema::create('hall_amenities', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60)->unique();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            $sort = 0;
            $seen = [];
            foreach (config('halls.amenities', []) as $name) {
                $name = trim((string) $name);
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;
                DB::table('hall_amenities')->insert([
                    'name'       => $name,
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (! Schema::hasTable('halls')) {
            Schema::create('halls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('hall_type_id')->constrained('hall_types')->restrictOnDelete();
                $table->string('name');
                $table->unsignedSmallInteger('capacity')->default(1);
                $table->text('description')->nullable();
                $table->json('amenities')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('halls');
        Schema::dropIfExists('hall_amenities');
        Schema::dropIfExists('hall_types');
    }
};
