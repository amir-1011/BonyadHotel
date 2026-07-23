<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\CancellationReason;
use App\Models\RefundPolicyTier;

class CancellationPolicyProvisioner
{
  public function seedForAccommodation(Accommodation|int $accommodation, bool $force = false): void
  {
    $model = $accommodation instanceof Accommodation
      ? $accommodation
      : Accommodation::query()->findOrFail($accommodation);
    $accommodationId = $model->id;

    $hasTiers = RefundPolicyTier::query()->where('accommodation_id', $accommodationId)->exists();
    $hasReasons = CancellationReason::query()->where('accommodation_id', $accommodationId)->exists();

    if ($hasTiers && $hasReasons) {
      return;
    }

    if (!$force && !($model->cancellation_policy_auto_seed ?? true)) {
      return;
    }

    foreach ($this->tierDefinitions() as $data) {
      RefundPolicyTier::query()->firstOrCreate(
        [
          'accommodation_id' => $accommodationId,
          'key'              => $data['key'],
        ],
        array_merge($data, ['accommodation_id' => $accommodationId]),
      );
    }

    foreach ($this->reasonDefinitions() as $data) {
      CancellationReason::query()->firstOrCreate(
        [
          'accommodation_id' => $accommodationId,
          'key'              => $data['key'],
        ],
        array_merge($data, ['accommodation_id' => $accommodationId]),
      );
    }

    app(RefundPolicyService::class)->clearCache($accommodationId);
  }

  public function restoreDefaultsForAccommodation(Accommodation|int $accommodation): void
  {
    app(CancellationPolicyBroadcastService::class)->copyGlobalPolicyToAccommodation($accommodation);
  }

  public function restoreHardcodedDefaultsForAccommodation(Accommodation|int $accommodation): void
  {
    $model = $accommodation instanceof Accommodation
      ? $accommodation
      : Accommodation::query()->findOrFail($accommodation);
    $accommodationId = $model->id;

    RefundPolicyTier::query()->where('accommodation_id', $accommodationId)->delete();
    CancellationReason::query()->where('accommodation_id', $accommodationId)->delete();

    $model->update(['cancellation_policy_auto_seed' => true]);
    $model->refresh();

    $this->seedForAccommodation($model, force: true);
  }

  /** @return list<array<string, mixed>> */
  public function tierDefinitions(): array
  {
    return [
      [
        'key'                     => 'tier_more_than_5_days',
        'label'                   => 'بیش از ۵ روز قبل از ورود',
        'min_days_before_checkin' => 6,
        'max_days_before_checkin' => null,
        'refund_percentage'       => 100,
        'sort_order'              => 1,
      ],
      [
        'key'                     => 'tier_3_to_5_days',
        'label'                   => '۳ تا ۵ روز قبل از ورود',
        'min_days_before_checkin' => 3,
        'max_days_before_checkin' => 5,
        'refund_percentage'       => 80,
        'sort_order'              => 2,
      ],
      [
        'key'                     => 'tier_1_to_2_days',
        'label'                   => '۱ تا ۲ روز قبل از ورود',
        'min_days_before_checkin' => 1,
        'max_days_before_checkin' => 2,
        'refund_percentage'       => 70,
        'sort_order'              => 3,
      ],
      [
        'key'                     => 'tier_checkin_day',
        'label'                   => 'همان روز ورود',
        'min_days_before_checkin' => 0,
        'max_days_before_checkin' => 0,
        'refund_percentage'       => 60,
        'sort_order'              => 4,
      ],
      [
        'key'                     => 'tier_mid_stay',
        'label'                   => 'پس از ورود (در حین استفاده)',
        'min_days_before_checkin' => null,
        'max_days_before_checkin' => -1,
        'refund_percentage'       => 50,
        'sort_order'              => 5,
      ],
    ];
  }

  /** @return list<array<string, mixed>> */
  public function reasonDefinitions(): array
  {
    return [
      [
        'key'        => 'travel_schedule_change',
        'label'      => 'تغییر برنامه سفر یا زمان‌بندی',
        'is_custom'  => false,
        'is_active'  => true,
        'sort_order' => 1,
      ],
      [
        'key'        => 'financial_issue',
        'label'      => 'مشکل مالی',
        'is_custom'  => false,
        'is_active'  => true,
        'sort_order' => 2,
      ],
      [
        'key'        => 'dissatisfaction',
        'label'      => 'عدم رضایت از خدمات یا اقامتگاه',
        'is_custom'  => false,
        'is_active'  => true,
        'sort_order' => 3,
      ],
      [
        'key'        => 'personal_family',
        'label'      => 'مشکل شخصی یا خانوادگی',
        'is_custom'  => false,
        'is_active'  => true,
        'sort_order' => 4,
      ],
      [
        'key'        => 'custom_other',
        'label'      => 'سایر (دلخواه)',
        'is_custom'  => true,
        'is_active'  => true,
        'sort_order' => 99,
      ],
    ];
  }
}
