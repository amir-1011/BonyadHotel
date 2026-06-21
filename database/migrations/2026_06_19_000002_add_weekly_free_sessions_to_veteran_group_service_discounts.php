<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veteran_group_service_discounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekly_free_sessions')->default(0)->after('free_sessions_eligible');
        });

        if (!Schema::hasColumn('veteran_groups', 'weekly_free_sessions')) {
            return;
        }

        $rows = DB::table('veteran_group_service_discounts as d')
            ->join('veteran_groups as g', 'g.id', '=', 'd.veteran_group_id')
            ->where('d.free_sessions_eligible', true)
            ->where('g.weekly_free_sessions', '>', 0)
            ->select('d.id', 'g.weekly_free_sessions')
            ->get();

        foreach ($rows as $row) {
            DB::table('veteran_group_service_discounts')
                ->where('id', $row->id)
                ->update(['weekly_free_sessions' => $row->weekly_free_sessions]);
        }
    }

    public function down(): void
    {
        Schema::table('veteran_group_service_discounts', function (Blueprint $table) {
            $table->dropColumn('weekly_free_sessions');
        });
    }
};
