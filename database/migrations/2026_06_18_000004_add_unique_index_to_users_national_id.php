<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** OTP placeholder national ID assigned before profile completion */
    private const PLACEHOLDER_NATIONAL_ID = '4440000008';

    public function up(): void
    {
        // Clear placeholder IDs — they are not real national IDs and cause duplicate-key conflicts.
        DB::table('users')
            ->where('national_id', self::PLACEHOLDER_NATIONAL_ID)
            ->update(['national_id' => null]);

        // For any remaining duplicates, keep the best candidate and null out the rest.
        $duplicateIds = DB::table('users')
            ->select('national_id')
            ->whereNotNull('national_id')
            ->groupBy('national_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('national_id');

        foreach ($duplicateIds as $nationalId) {
            $keepId = DB::table('users')
                ->where('national_id', $nationalId)
                ->orderByDesc('national_id_verified_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('users')
                ->where('national_id', $nationalId)
                ->where('id', '!=', $keepId)
                ->update(['national_id' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('national_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
        });
    }
};
