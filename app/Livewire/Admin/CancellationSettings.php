<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\CancellationReason;
use App\Models\RefundPolicyTier;
use App\Livewire\Concerns\ManagesDashboardAccommodationFilter;
use App\Services\CancellationPolicyBroadcastService;
use App\Services\RefundPolicyService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'تنظیمات کنسلی و استرداد وجه', 'pageTitle' => 'کنسلی و استرداد وجه'])]
class CancellationSettings extends Component
{
  use ManagesDashboardAccommodationFilter {
    onDashboardAccommodationFilterChanged as protected triggerDashboardAccommodationFilterChanged;
  }

  public string $tab = 'tiers';

  /** @var array<int, array<string, mixed>> */
  public array $tiers = [];

  /** @var array<int, array<string, mixed>> */
  public array $reasons = [];

  public function mount(CancellationPolicyBroadcastService $broadcast): void
  {
    $this->bootDashboardAccommodationFilter();
    $broadcast->ensureAllAccommodationsHavePolicy();
    $this->loadData($broadcast);
  }

  protected function resolveDashboardAccommodationOptions(): array
  {
    return Accommodation::query()
      ->orderBy('name')
      ->get(['id', 'name'])
      ->map(fn (Accommodation $acc) => ['id' => (int) $acc->id, 'name' => (string) $acc->name])
      ->values()
      ->all();
  }

  protected function onDashboardAccommodationFilterChanged(): void
  {
    $this->triggerDashboardAccommodationFilterChanged();
    $this->loadData();
  }

  /** @return array<int> */
  protected function scopedAccommodationIds(): array
  {
    return $this->effectiveDashboardAccommodationIds();
  }

  protected function isAllAccommodationsSelected(): bool
  {
    return $this->dashboardAccommodationAllSelected;
  }

  public function loadData(?CancellationPolicyBroadcastService $broadcast = null): void
  {
    $broadcast ??= app(CancellationPolicyBroadcastService::class);
    $scopedIds = $this->scopedAccommodationIds();
    $referenceId = $broadcast->referenceAccommodationId($scopedIds);

    if (!$referenceId || $scopedIds === []) {
      $this->tiers = [];
      $this->reasons = [];

      return;
    }

    $tierKeys = RefundPolicyTier::query()
      ->whereIn('accommodation_id', $scopedIds)
      ->orderBy('sort_order')
      ->pluck('key')
      ->unique()
      ->values()
      ->all();

    $tiersByKey = RefundPolicyTier::query()
      ->whereIn('accommodation_id', $scopedIds)
      ->ordered()
      ->get()
      ->groupBy('key');

    $this->tiers = collect($tierKeys)
      ->map(function (string $key) use ($tiersByKey, $referenceId) {
        $rows = $tiersByKey->get($key, collect());
        $tier = $rows->firstWhere('accommodation_id', $referenceId) ?? $rows->first();
        if (!$tier) {
          return null;
        }

        return [
          'key'                     => $tier->key,
          'label'                   => $tier->label,
          'min_days_before_checkin' => $tier->min_days_before_checkin,
          'max_days_before_checkin' => $tier->max_days_before_checkin,
          'refund_percentage'       => $tier->refund_percentage,
        ];
      })
      ->filter()
      ->values()
      ->all();

    $reasonKeys = CancellationReason::query()
      ->whereIn('accommodation_id', $scopedIds)
      ->orderBy('sort_order')
      ->pluck('key')
      ->unique()
      ->values()
      ->all();

    $reasonsByKey = CancellationReason::query()
      ->whereIn('accommodation_id', $scopedIds)
      ->ordered()
      ->get()
      ->groupBy('key');

    $this->reasons = collect($reasonKeys)
      ->map(function (string $key) use ($reasonsByKey, $referenceId) {
        $rows = $reasonsByKey->get($key, collect());
        $reason = $rows->firstWhere('accommodation_id', $referenceId) ?? $rows->first();
        if (!$reason) {
          return null;
        }

        return [
          'key'       => $reason->key,
          'label'     => $reason->label,
          'is_custom' => $reason->is_custom,
          'is_active' => $reason->is_active,
        ];
      })
      ->filter()
      ->values()
      ->all();
  }

  protected function scopedSaveMessage(string $action): string
  {
    if ($this->isAllAccommodationsSelected()) {
      return "{$action} برای همه اقامتگاه‌ها ذخیره شد.";
    }

    $count = count($this->scopedAccommodationIds());

    return "{$action} برای {$count} اقامتگاه انتخاب‌شده ذخیره شد.";
  }

  public function addTier(): void
  {
    $this->tiers[] = [
      'key'                     => 'custom_tier_' . time(),
      'label'                   => '',
      'min_days_before_checkin' => null,
      'max_days_before_checkin' => null,
      'refund_percentage'       => 0,
    ];
  }

  public function removeTier(int $index, CancellationPolicyBroadcastService $broadcast): void
  {
    $tier = $this->tiers[$index] ?? null;
    if ($tier && !empty($tier['key'])) {
      $broadcast->removeTierFromAllAccommodations($tier['key'], $this->scopedAccommodationIds());
    }

    unset($this->tiers[$index]);
    $this->tiers = array_values($this->tiers);
    $this->dispatch('toast', type: 'success', message: 'بازه حذف شد.');
  }

  public function saveTiers(CancellationPolicyBroadcastService $broadcast): void
  {
    $this->validate([
      'tiers.*.label'                   => ['nullable', 'string', 'max:200'],
      'tiers.*.min_days_before_checkin' => ['nullable', 'integer'],
      'tiers.*.max_days_before_checkin' => ['nullable', 'integer'],
      'tiers.*.refund_percentage'       => ['required', 'integer', 'min:0', 'max:100'],
    ]);

    $scopedIds = $this->scopedAccommodationIds();
    if ($scopedIds === []) {
      $this->dispatch('toast', type: 'error', message: 'حداقل یک اقامتگاه را انتخاب کنید.');

      return;
    }

    $existingKeys = RefundPolicyTier::query()
      ->whereIn('accommodation_id', $scopedIds)
      ->pluck('key')
      ->unique()
      ->all();

    foreach ($this->tiers as $index => $row) {
      $key = $row['key'] ?? ('custom_tier_' . time() . '_' . $index);
      $payload = [
        'label'                   => $row['label'] !== '' ? $row['label'] : null,
        'min_days_before_checkin' => $row['min_days_before_checkin'] !== '' ? $row['min_days_before_checkin'] : null,
        'max_days_before_checkin' => $row['max_days_before_checkin'] !== '' ? $row['max_days_before_checkin'] : null,
        'refund_percentage'       => (int) $row['refund_percentage'],
        'sort_order'              => $index + 1,
      ];

      if (in_array($key, $existingKeys, true)) {
        $broadcast->syncTierByKey($key, $payload, $scopedIds);
      } else {
        $broadcast->addTierToAllAccommodations(array_merge($payload, ['key' => $key]), $scopedIds);
      }

      $this->tiers[$index]['key'] = $key;
    }

    $this->loadData($broadcast);
    $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('بازه‌های بازگشت وجه'));
  }

  public function addReason(): void
  {
    $this->reasons[] = [
      'key'       => 'custom_reason_' . time(),
      'label'     => '',
      'is_custom' => false,
      'is_active' => true,
    ];
  }

  public function removeReason(int $index, CancellationPolicyBroadcastService $broadcast): void
  {
    $reason = $this->reasons[$index] ?? null;
    if ($reason && !empty($reason['key'])) {
      $broadcast->removeReasonFromAllAccommodations($reason['key'], $this->scopedAccommodationIds());
    }

    unset($this->reasons[$index]);
    $this->reasons = array_values($this->reasons);
    $this->dispatch('toast', type: 'success', message: 'دلیل حذف شد.');
  }

  public function saveReasons(CancellationPolicyBroadcastService $broadcast): void
  {
    $this->validate([
      'reasons.*.label' => ['required', 'string', 'max:200'],
    ]);

    $scopedIds = $this->scopedAccommodationIds();
    if ($scopedIds === []) {
      $this->dispatch('toast', type: 'error', message: 'حداقل یک اقامتگاه را انتخاب کنید.');

      return;
    }

    $existingKeys = CancellationReason::query()
      ->whereIn('accommodation_id', $scopedIds)
      ->pluck('key')
      ->unique()
      ->all();

    foreach ($this->reasons as $index => $row) {
      $key = $row['key'] ?? ('custom_reason_' . time() . '_' . $index);
      $payload = [
        'label'      => $row['label'],
        'is_custom'  => (bool) ($row['is_custom'] ?? false),
        'is_active'  => (bool) ($row['is_active'] ?? true),
        'sort_order' => $index + 1,
      ];

      if (in_array($key, $existingKeys, true)) {
        $broadcast->syncReasonByKey($key, $payload, $scopedIds);
      } else {
        $broadcast->addReasonToAllAccommodations(array_merge($payload, ['key' => $key]), $scopedIds);
      }

      $this->reasons[$index]['key'] = $key;
    }

    $this->loadData($broadcast);
    $this->dispatch('toast', type: 'success', message: $this->scopedSaveMessage('دلایل کنسلی'));
  }

  public function render(CancellationPolicyBroadcastService $broadcast)
  {
    $scopedIds = $this->scopedAccommodationIds();
    $totalAccommodations = count($this->dashboardAccommodationOptionList());
    $scopedCount = count($scopedIds);

    return view('livewire.concerns.cancellation-policy-settings', [
      'accommodation'                 => null,
      'backRoute'                     => null,
      'panel'                         => 'admin',
      'accommodationCount'            => $totalAccommodations,
      'scopedAccommodationCount'      => $scopedCount,
      'scopedAccommodationIds'        => $scopedIds,
      'isAllAccommodationsSelected'   => $this->isAllAccommodationsSelected(),
      'tierAccommodationsByKey'       => $broadcast->tierAccommodationsByKey($scopedIds),
      'reasonAccommodationsByKey'     => $broadcast->reasonAccommodationsByKey($scopedIds),
      'filterKey'                       => $this->dashboardAccommodationFilterKey(),
      'dashboardAccommodationOptions' => $this->dashboardAccommodationOptionList(),
    ]);
  }
}
