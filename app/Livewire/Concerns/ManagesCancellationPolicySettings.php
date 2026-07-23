<?php

namespace App\Livewire\Concerns;

use App\Models\Accommodation;
use App\Models\CancellationReason;
use App\Models\RefundPolicyTier;
use App\Services\CancellationPolicyProvisioner;
use App\Services\RefundPolicyService;

trait ManagesCancellationPolicySettings
{
  use AssertsHostPermissions;
  public Accommodation $accommodation;

  public string $tab = 'tiers';

  /** @var array<int, array<string, mixed>> */
  public array $tiers = [];

  /** @var array<int, array<string, mixed>> */
  public array $reasons = [];

  protected function bootCancellationPolicySettings(Accommodation $accommodation): void
  {
    $this->accommodation = $accommodation;
    app(CancellationPolicyProvisioner::class)->seedForAccommodation($accommodation);
    $this->loadCancellationPolicyData();
  }

  public function loadCancellationPolicyData(): void
  {
    $accommodationId = $this->accommodation->id;

    $this->tiers = RefundPolicyTier::query()
      ->forAccommodation($accommodationId)
      ->ordered()
      ->get()
      ->map(fn (RefundPolicyTier $tier) => [
        'id'                      => $tier->id,
        'key'                     => $tier->key,
        'label'                   => $tier->label,
        'min_days_before_checkin' => $tier->min_days_before_checkin,
        'max_days_before_checkin' => $tier->max_days_before_checkin,
        'refund_percentage'       => $tier->refund_percentage,
      ])->values()->all();

    $this->reasons = CancellationReason::query()
      ->forAccommodation($accommodationId)
      ->ordered()
      ->get()
      ->map(fn (CancellationReason $reason) => [
        'id'        => $reason->id,
        'key'       => $reason->key,
        'label'     => $reason->label,
        'is_custom' => $reason->is_custom,
        'is_active' => $reason->is_active,
      ])->values()->all();
  }

  public function addTier(): void
  {
    $this->assertHostCan('accommodations.cancellation-policy', 'edit');
    $this->tiers[] = [
      'id'                      => null,
      'key'                     => 'custom_tier_' . time(),
      'label'                   => '',
      'min_days_before_checkin' => null,
      'max_days_before_checkin' => null,
      'refund_percentage'       => 0,
    ];
  }

  public function removeTier(int $index): void
  {
    $this->assertHostCan('accommodations.cancellation-policy', 'edit');
    $tier = $this->tiers[$index] ?? null;
    if ($tier && !empty($tier['id'])) {
      RefundPolicyTier::query()
        ->where('accommodation_id', $this->accommodation->id)
        ->where('id', $tier['id'])
        ->delete();
    }

    unset($this->tiers[$index]);
    $this->tiers = array_values($this->tiers);
    $this->clearCancellationPolicyCache();
    $this->dispatch('toast', type: 'success', message: 'بازه حذف شد.');
  }

  public function saveTiers(): void
  {
    $this->assertHostCan('accommodations.cancellation-policy', 'edit');
    $this->validate([
      'tiers.*.label'                   => ['nullable', 'string', 'max:200'],
      'tiers.*.min_days_before_checkin' => ['nullable', 'integer'],
      'tiers.*.max_days_before_checkin' => ['nullable', 'integer'],
      'tiers.*.refund_percentage'       => ['required', 'integer', 'min:0', 'max:100'],
    ]);

    $accommodationId = $this->accommodation->id;

    foreach ($this->tiers as $index => $row) {
      $payload = [
        'label'                   => $row['label'] !== '' ? $row['label'] : null,
        'min_days_before_checkin' => $row['min_days_before_checkin'] !== '' ? $row['min_days_before_checkin'] : null,
        'max_days_before_checkin' => $row['max_days_before_checkin'] !== '' ? $row['max_days_before_checkin'] : null,
        'refund_percentage'       => (int) $row['refund_percentage'],
        'sort_order'              => $index + 1,
      ];

      if (!empty($row['id'])) {
        RefundPolicyTier::query()
          ->where('accommodation_id', $accommodationId)
          ->where('id', $row['id'])
          ->update($payload);
      } else {
        $created = RefundPolicyTier::create(array_merge($payload, [
          'accommodation_id' => $accommodationId,
          'key'              => $row['key'] ?? ('custom_tier_' . time() . '_' . $index),
        ]));
        $this->tiers[$index]['id'] = $created->id;
      }
    }

    $this->clearCancellationPolicyCache();
    $this->loadCancellationPolicyData();
    $this->dispatch('toast', type: 'success', message: 'بازه‌های بازگشت وجه ذخیره شد.');
  }

  public function addReason(): void
  {
    $this->reasons[] = [
      'id'        => null,
      'key'       => 'custom_reason_' . time(),
      'label'     => '',
      'is_custom' => false,
      'is_active' => true,
    ];
  }

  public function removeReason(int $index): void
  {
    $reason = $this->reasons[$index] ?? null;
    if ($reason && !empty($reason['id'])) {
      CancellationReason::query()
        ->where('accommodation_id', $this->accommodation->id)
        ->where('id', $reason['id'])
        ->delete();
    }

    unset($this->reasons[$index]);
    $this->reasons = array_values($this->reasons);
    $this->dispatch('toast', type: 'success', message: 'دلیل حذف شد.');
  }

  public function saveReasons(): void
  {
    $this->validate([
      'reasons.*.label' => ['required', 'string', 'max:200'],
    ]);

    $accommodationId = $this->accommodation->id;

    foreach ($this->reasons as $index => $row) {
      $payload = [
        'label'      => $row['label'],
        'is_custom'  => (bool) ($row['is_custom'] ?? false),
        'is_active'  => (bool) ($row['is_active'] ?? true),
        'sort_order' => $index + 1,
      ];

      if (!empty($row['id'])) {
        CancellationReason::query()
          ->where('accommodation_id', $accommodationId)
          ->where('id', $row['id'])
          ->update($payload);
      } else {
        $created = CancellationReason::create(array_merge($payload, [
          'accommodation_id' => $accommodationId,
          'key'              => $row['key'] ?? ('custom_reason_' . time() . '_' . $index),
        ]));
        $this->reasons[$index]['id'] = $created->id;
      }
    }

    $this->loadCancellationPolicyData();
    $this->dispatch('toast', type: 'success', message: 'دلایل کنسلی ذخیره شد.');
  }

  public function restoreDefaultCancellationPolicy(): void
  {
    app(CancellationPolicyProvisioner::class)->restoreDefaultsForAccommodation($this->accommodation);
    $this->accommodation->refresh();
    $this->loadCancellationPolicyData();
    $this->dispatch('toast', type: 'success', message: 'سیاست کنسلی از تنظیمات سراسری بازگردانی شد.');
  }

  protected function clearCancellationPolicyCache(): void
  {
    app(RefundPolicyService::class)->clearCache($this->accommodation->id);
  }
}
