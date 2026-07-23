<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_policy_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            // Days remaining before check-in date. Null min = no lower bound, null max = no upper bound.
            // Negative values represent days AFTER check-in (i.e. mid-stay / after arrival).
            $table->integer('min_days_before_checkin')->nullable();
            $table->integer('max_days_before_checkin')->nullable();
            $table->unsignedTinyInteger('refund_percentage')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('refund_policy_tiers')->insert([
            ['label' => 'بیش از ۵ روز قبل از ورود', 'min_days_before_checkin' => 6, 'max_days_before_checkin' => null, 'refund_percentage' => 100, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['label' => '۳ تا ۵ روز قبل از ورود', 'min_days_before_checkin' => 3, 'max_days_before_checkin' => 5, 'refund_percentage' => 80, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['label' => '۱ تا ۲ روز قبل از ورود', 'min_days_before_checkin' => 1, 'max_days_before_checkin' => 2, 'refund_percentage' => 70, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'همان روز ورود', 'min_days_before_checkin' => 0, 'max_days_before_checkin' => 0, 'refund_percentage' => 60, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['label' => 'پس از ورود (در حین استفاده)', 'min_days_before_checkin' => null, 'max_days_before_checkin' => -1, 'refund_percentage' => 50, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_policy_tiers');
    }
};
