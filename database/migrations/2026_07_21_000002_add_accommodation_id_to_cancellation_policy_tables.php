<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasColumn('refund_policy_tiers', 'accommodation_id')) {
      Schema::table('refund_policy_tiers', function (Blueprint $table) {
        $table->foreignId('accommodation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        $table->string('key', 80)->nullable()->after('accommodation_id');
      });
    }

    if (! Schema::hasColumn('cancellation_reasons', 'accommodation_id')) {
      Schema::table('cancellation_reasons', function (Blueprint $table) {
        $table->foreignId('accommodation_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        $table->string('key', 80)->nullable()->after('accommodation_id');
      });
    }

    if (! Schema::hasColumn('accommodations', 'cancellation_policy_auto_seed')) {
      Schema::table('accommodations', function (Blueprint $table) {
        $table->boolean('cancellation_policy_auto_seed')->default(true)->after('veteran_policy_auto_seed');
      });
    }

    // Adding columns after `id` can drop AUTO_INCREMENT on some MySQL versions.
    DB::statement('ALTER TABLE refund_policy_tiers MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

    $tierKeys = [
      1 => 'tier_more_than_5_days',
      2 => 'tier_3_to_5_days',
      3 => 'tier_1_to_2_days',
      4 => 'tier_checkin_day',
      5 => 'tier_mid_stay',
    ];

    $reasonKeys = [
      1 => 'travel_schedule_change',
      2 => 'financial_issue',
      3 => 'dissatisfaction',
      4 => 'personal_family',
      99 => 'custom_other',
    ];

    $globalTiers = DB::table('refund_policy_tiers')->orderBy('sort_order')->get();
    $globalReasons = DB::table('cancellation_reasons')->orderBy('sort_order')->get();
    $accommodationIds = DB::table('accommodations')->orderBy('id')->pluck('id');

    foreach ($accommodationIds as $accommodationId) {
      foreach ($globalTiers as $tier) {
        $key = $tierKeys[(int) $tier->sort_order] ?? ('tier_' . $tier->id);

        DB::table('refund_policy_tiers')->insert([
          'accommodation_id'          => $accommodationId,
          'key'                       => $key,
          'label'                     => $tier->label,
          'min_days_before_checkin'   => $tier->min_days_before_checkin,
          'max_days_before_checkin'   => $tier->max_days_before_checkin,
          'refund_percentage'         => $tier->refund_percentage,
          'sort_order'                => $tier->sort_order,
          'created_at'                => now(),
          'updated_at'                => now(),
        ]);
      }

      foreach ($globalReasons as $reason) {
        $key = $reasonKeys[(int) $reason->sort_order] ?? ('reason_' . $reason->id);

        DB::table('cancellation_reasons')->insert([
          'accommodation_id' => $accommodationId,
          'key'              => $key,
          'label'            => $reason->label,
          'is_custom'        => $reason->is_custom,
          'is_active'        => $reason->is_active,
          'sort_order'       => $reason->sort_order,
          'created_at'       => now(),
          'updated_at'       => now(),
        ]);
      }
    }

    DB::table('refund_policy_tiers')->whereNull('accommodation_id')->delete();
    DB::table('cancellation_reasons')->whereNull('accommodation_id')->delete();

    Schema::table('refund_policy_tiers', function (Blueprint $table) {
      $table->foreignId('accommodation_id')->nullable(false)->change();
      $table->string('key', 80)->nullable(false)->change();
      $table->unique(['accommodation_id', 'key']);
    });

    Schema::table('cancellation_reasons', function (Blueprint $table) {
      $table->foreignId('accommodation_id')->nullable(false)->change();
      $table->string('key', 80)->nullable(false)->change();
      $table->unique(['accommodation_id', 'key']);
    });
  }

  public function down(): void
  {
    $now = now();

    $sampleTiers = DB::table('refund_policy_tiers')
      ->select('label', 'min_days_before_checkin', 'max_days_before_checkin', 'refund_percentage', 'sort_order')
      ->orderBy('accommodation_id')
      ->orderBy('sort_order')
      ->get()
      ->unique('sort_order');

    $sampleReasons = DB::table('cancellation_reasons')
      ->select('label', 'is_custom', 'is_active', 'sort_order')
      ->orderBy('accommodation_id')
      ->orderBy('sort_order')
      ->get()
      ->unique('sort_order');

    DB::table('refund_policy_tiers')->truncate();
    DB::table('cancellation_reasons')->truncate();

    foreach ($sampleTiers as $tier) {
      DB::table('refund_policy_tiers')->insert([
        'label'                   => $tier->label,
        'min_days_before_checkin' => $tier->min_days_before_checkin,
        'max_days_before_checkin' => $tier->max_days_before_checkin,
        'refund_percentage'       => $tier->refund_percentage,
        'sort_order'              => $tier->sort_order,
        'created_at'              => $now,
        'updated_at'              => $now,
      ]);
    }

    foreach ($sampleReasons as $reason) {
      DB::table('cancellation_reasons')->insert([
        'label'      => $reason->label,
        'is_custom'  => $reason->is_custom,
        'is_active'  => $reason->is_active,
        'sort_order' => $reason->sort_order,
        'created_at' => $now,
        'updated_at' => $now,
      ]);
    }

    Schema::table('accommodations', function (Blueprint $table) {
      $table->dropColumn('cancellation_policy_auto_seed');
    });

    Schema::table('refund_policy_tiers', function (Blueprint $table) {
      $table->dropUnique(['accommodation_id', 'key']);
      $table->dropConstrainedForeignId('accommodation_id');
      $table->dropColumn('key');
    });

    Schema::table('cancellation_reasons', function (Blueprint $table) {
      $table->dropUnique(['accommodation_id', 'key']);
      $table->dropConstrainedForeignId('accommodation_id');
      $table->dropColumn('key');
    });
  }
};
