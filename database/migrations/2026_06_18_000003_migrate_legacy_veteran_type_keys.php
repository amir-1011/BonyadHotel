<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $legacyMap = [
        'veteran_70_plus'       => 'veteran_70_spouses',
        'veteran_50_69'         => 'veteran_50_69_dependents',
        'veteran_25_49'         => 'veteran_25_49_dependents',
        'martyr_family'         => 'martyr_spouse_dependents',
        'freed_prisoner_family' => 'freed_prisoner_dependents',
    ];

    public function up(): void
    {
        foreach ($this->legacyMap as $old => $new) {
            DB::table('users')
                ->where('veteran_type', $old)
                ->update(['veteran_type' => $new]);

            DB::table('bookings')
                ->where('veteran_type_applied', $old)
                ->update(['veteran_type_applied' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->legacyMap as $old => $new) {
            DB::table('users')
                ->where('veteran_type', $new)
                ->update(['veteran_type' => $old]);

            DB::table('bookings')
                ->where('veteran_type_applied', $new)
                ->update(['veteran_type_applied' => $old]);
        }
    }
};
