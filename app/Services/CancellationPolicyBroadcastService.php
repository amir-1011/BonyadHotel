<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\CancellationReason;
use App\Models\RefundPolicyTier;
use Illuminate\Support\Facades\DB;

class CancellationPolicyBroadcastService
{
  public function __construct(
    private readonly CancellationPolicyProvisioner $provisioner,
    private readonly RefundPolicyService $refundPolicy,
  ) {}

  public function ensureAllAccommodationsHavePolicy(): void
  {
    foreach (Accommodation::query()->pluck('id') as $accommodationId) {
      $this->provisioner->seedForAccommodation($accommodationId);
    }
  }

  public function referenceAccommodationId(?array $accommodationIds = null): ?int
  {
    $query = RefundPolicyTier::query()->orderBy('accommodation_id');

    if ($accommodationIds !== null) {
      $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
    }

    return $query->value('accommodation_id');
  }

  /**
   * @param  array<int>|null  $scopeAccommodationIds
   * @return array<string, list<array{id: int, name: string}>>
   */
  public function tierAccommodationsByKey(?array $scopeAccommodationIds = null): array
  {
    return $this->policyAccommodationsByKey(RefundPolicyTier::class, $scopeAccommodationIds);
  }

  /**
   * @param  array<int>|null  $scopeAccommodationIds
   * @return array<string, list<array{id: int, name: string}>>
   */
  public function reasonAccommodationsByKey(?array $scopeAccommodationIds = null): array
  {
    return $this->policyAccommodationsByKey(CancellationReason::class, $scopeAccommodationIds);
  }

  /**
   * @param  array<int>|null  $accommodationIds
   * @return list<int>
   */
  public function resolveAccommodationIds(?array $accommodationIds): array
  {
    if ($accommodationIds === null) {
      return Accommodation::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    $allowed = Accommodation::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

    return array_values(array_intersect(array_map('intval', $accommodationIds), $allowed));
  }

  /**
   * @param  class-string<RefundPolicyTier|CancellationReason>  $modelClass
   * @param  array<int>|null  $scopeAccommodationIds
   * @return array<string, list<array{id: int, name: string}>>
   */
  private function policyAccommodationsByKey(string $modelClass, ?array $scopeAccommodationIds): array
  {
    $ids = $this->resolveAccommodationIds($scopeAccommodationIds);

    if ($ids === []) {
      return [];
    }

    $rows = $modelClass::query()
      ->whereIn('accommodation_id', $ids)
      ->with('accommodation:id,name')
      ->orderBy('accommodation_id')
      ->get(['id', 'accommodation_id', 'key']);

    $map = [];
    foreach ($rows as $row) {
      $map[$row->key] ??= [];
      $accId = (int) $row->accommodation_id;
      if (!collect($map[$row->key])->contains('id', $accId)) {
        $map[$row->key][] = [
          'id'   => $accId,
          'name' => (string) ($row->accommodation?->name ?? ''),
        ];
      }
    }

    return $map;
  }

  /**
   * @param  array<string, mixed>  $attributes
   * @param  array<int>|null  $accommodationIds
   */
  public function syncTierByKey(string $key, array $attributes, ?array $accommodationIds = null): void
  {
    $this->ensureAllAccommodationsHavePolicy();

    $query = RefundPolicyTier::query()->where('key', $key);

    if ($accommodationIds !== null) {
      $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
    }

    $query->update($attributes);

    $this->clearCachesForScope($accommodationIds);
  }

  /**
   * @param  array<string, mixed>  $attributes
   * @param  array<int>|null  $accommodationIds
   */
  public function syncReasonByKey(string $key, array $attributes, ?array $accommodationIds = null): void
  {
    $this->ensureAllAccommodationsHavePolicy();

    $query = CancellationReason::query()->where('key', $key);

    if ($accommodationIds !== null) {
      $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
    }

    $query->update($attributes);
  }

  /**
   * @param  array<string, mixed>  $attributes
   * @param  array<int>|null  $accommodationIds
   */
  public function addTierToAllAccommodations(array $attributes, ?array $accommodationIds = null): void
  {
    $ids = $this->resolveAccommodationIds($accommodationIds);

    foreach ($ids as $accommodationId) {
      RefundPolicyTier::query()->firstOrCreate(
        [
          'accommodation_id' => $accommodationId,
          'key'              => $attributes['key'],
        ],
        array_merge($attributes, ['accommodation_id' => $accommodationId]),
      );
    }

    $this->clearCachesForScope($accommodationIds);
  }

  /**
   * @param  array<string, mixed>  $attributes
   * @param  array<int>|null  $accommodationIds
   */
  public function addReasonToAllAccommodations(array $attributes, ?array $accommodationIds = null): void
  {
    $ids = $this->resolveAccommodationIds($accommodationIds);

    foreach ($ids as $accommodationId) {
      CancellationReason::query()->firstOrCreate(
        [
          'accommodation_id' => $accommodationId,
          'key'              => $attributes['key'],
        ],
        array_merge($attributes, ['accommodation_id' => $accommodationId]),
      );
    }
  }

  public function removeTierFromAllAccommodations(string $key, ?array $accommodationIds = null): void
  {
    $query = RefundPolicyTier::query()->where('key', $key);

    if ($accommodationIds !== null) {
      $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
    }

    $query->delete();
    $this->clearCachesForScope($accommodationIds);
  }

  public function removeReasonFromAllAccommodations(string $key, ?array $accommodationIds = null): void
  {
    $query = CancellationReason::query()->where('key', $key);

    if ($accommodationIds !== null) {
      $query->whereIn('accommodation_id', $this->resolveAccommodationIds($accommodationIds));
    }

    $query->delete();
  }

  public function copyGlobalPolicyToAccommodation(Accommodation|int $accommodation): void
  {
    $this->ensureAllAccommodationsHavePolicy();

    $target = $accommodation instanceof Accommodation
      ? $accommodation
      : Accommodation::query()->findOrFail($accommodation);
    $targetId = $target->id;

    $referenceId = $this->referenceAccommodationId();
    if (!$referenceId) {
      $this->provisioner->restoreHardcodedDefaultsForAccommodation($target);

      return;
    }

    $this->replaceAccommodationPolicyFromReference($targetId, $referenceId);

    $target->update(['cancellation_policy_auto_seed' => true]);
    $this->refundPolicy->clearCache($targetId);
  }

  private function replaceAccommodationPolicyFromReference(int $targetId, int $referenceId): void
  {
    DB::transaction(function () use ($targetId, $referenceId) {
      RefundPolicyTier::query()->where('accommodation_id', $targetId)->delete();
      CancellationReason::query()->where('accommodation_id', $targetId)->delete();

      foreach (RefundPolicyTier::query()->forAccommodation($referenceId)->ordered()->get() as $refTier) {
        $tier = $refTier->replicate();
        $tier->accommodation_id = $targetId;
        $tier->save();
      }

      foreach (CancellationReason::query()->forAccommodation($referenceId)->ordered()->get() as $refReason) {
        $reason = $refReason->replicate();
        $reason->accommodation_id = $targetId;
        $reason->save();
      }
    });
  }

  /** @param  array<int>|null  $accommodationIds */
  private function clearCachesForScope(?array $accommodationIds): void
  {
    $ids = $this->resolveAccommodationIds($accommodationIds);

    foreach ($ids as $accommodationId) {
      $this->refundPolicy->clearCache($accommodationId);
    }
  }
}
