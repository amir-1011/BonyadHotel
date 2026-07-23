<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_position_titles', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100)->unique();
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $sort = 0;

        foreach ($this->defaultLabels() as $label) {
            DB::table('host_position_titles')->insert([
                'label'       => $label,
                'is_system'   => true,
                'sort_order'  => ++$sort,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $userTitles = DB::table('users')
            ->whereNotNull('host_position_title')
            ->where('host_position_title', '!=', '')
            ->distinct()
            ->pluck('host_position_title');

        foreach ($userTitles as $label) {
            $label = trim((string) $label);

            if ($label === '' || in_array($label, $this->defaultLabels(), true)) {
                continue;
            }

            DB::table('host_position_titles')->insertOrIgnore([
                'label'       => $label,
                'is_system'   => false,
                'sort_order'  => 1000 + $sort++,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('host_position_titles');
    }

    /** @return list<string> */
    private function defaultLabels(): array
    {
        return [
            'معاون تخصصی',
            'مدیر تخصصی',
            'کارشناس تخصصی',
            'مدیر مجموعه',
            'مدیر مالی',
            'مدیر داخلی',
            'کارشناس فروش',
            'کارشناس پشتیبانی',
        ];
    }
};
