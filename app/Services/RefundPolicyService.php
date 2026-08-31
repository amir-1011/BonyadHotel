<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\RefundPolicyTier;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RefundPolicyService
{
  public const CACHE_TTL_SECONDS = 300;

  private ?int $accommodationId = null;

  public function forAccommodation(?int $accommodationId): self
  {
    $clone = clone $this;
    $clone->accommodationId = $accommodationId;

    return $clone;
  }

  public function cacheKey(?int $accommodationId = null): string
  {
    $id = $accommodationId ?? $this->accommodationId;

    return 'refund_policy_tiers:v2:' . ($id ?? 'global');
  }

  /**
   * @return Collection<int, RefundPolicyTier>
   */
  public function tiers(?int $accommodationId = null): Collection
  {
    $id = $accommodationId ?? $this->accommodationId;

    if ($id === null) {
      return collect();
    }

    return Cache::remember($this->cacheKey($id), self::CACHE_TTL_SECONDS, function () use ($id) {
      return RefundPolicyTier::query()->forAccommodation($id)->ordered()->get();
    });
  }

  public function clearCache(?int $accommodationId = null): void
  {
    if ($accommodationId !== null) {
      Cache::forget($this->cacheKey($accommodationId));

      return;
    }

    if ($this->accommodationId !== null) {
      Cache::forget($this->cacheKey($this->accommodationId));
    }
  }

  /**
   * Number of days remaining before check-in (positive = future, 0 = today, negative = already started).
   */
  public function daysBeforeCheckin(Booking $booking, ?Carbon $now = null): int
  {
    $checkIn = Carbon::parse($booking->check_in)->startOfDay();
    $today = ($now ?? now())->copy()->startOfDay();

    return (int) round(($checkIn->getTimestamp() - $today->getTimestamp()) / 86400);
  }

  public function refundPercentageForDays(int $daysBeforeCheckin, ?int $accommodationId = null): int
  {
    $tier = $this->tiers($accommodationId)->first(
      fn (RefundPolicyTier $tier) => $tier->matches($daysBeforeCheckin),
    );

    return $tier ? (int) $tier->refund_percentage : 0;
  }

  /**
   * Number of nights already elapsed since check-in (0 before/on the check-in day).
   */
  public function nightsElapsed(Booking $booking, ?Carbon $now = null): int
  {
    $daysBeforeCheckin = $this->daysBeforeCheckin($booking, $now);
    $totalNights = max(1, (int) $booking->nights);

    return min($totalNights, max(0, -$daysBeforeCheckin));
  }

  /**
   * @return array{days:int, percentage:int, amount:int, nights_total:int, nights_elapsed:int, nights_remaining:int, basis_amount:int, is_mid_stay:bool}
   */
  public function previewForBooking(Booking $booking, ?Carbon $now = null): array
  {
    $accommodationId = (int) $booking->accommodation_id;
    $days = $this->daysBeforeCheckin($booking, $now);

    $totalNights = max(1, (int) $booking->nights);
    $isMidStay = $days < 0;
    $nightsElapsed = $isMidStay ? $this->nightsElapsed($booking, $now) : 0;
    $nightsRemaining = max(0, $totalNights - $nightsElapsed);

    if ($booking->skipsCancellationPenalties()) {
      return $this->medicalPreview($booking, $days, $totalNights, $nightsElapsed, $nightsRemaining, $isMidStay);
    }

    $percentage = $this->refundPercentageForDays($days, $accommodationId);

    $basisAmount = $isMidStay
      ? (int) round($booking->total_price * $nightsRemaining / $totalNights)
      : (int) $booking->total_price;

    $amount = (int) round($basisAmount * $percentage / 100);

    return [
      'days'             => $days,
      'percentage'       => $percentage,
      'amount'           => $amount,
      'nights_total'     => $totalNights,
      'nights_elapsed'   => $nightsElapsed,
      'nights_remaining' => $nightsRemaining,
      'basis_amount'     => $basisAmount,
      'is_mid_stay'      => $isMidStay,
      'guest_paid'       => true,
    ];
  }

  /**
   * Medical stays have no cancellation penalty. The guest did not pay, so guest refund is 0.
   * Unused nights are credited against the employer (Day Insurance) debt.
   *
   * @return array{days:int, percentage:int, amount:int, nights_total:int, nights_elapsed:int, nights_remaining:int, basis_amount:int, is_mid_stay:bool, guest_paid:bool, medical_used_stay_amount:int, employer_debt_after:int}
   */
  private function medicalPreview(
    Booking $booking,
    int $days,
    int $totalNights,
    int $nightsElapsed,
    int $nightsRemaining,
    bool $isMidStay,
  ): array {
    $stayPerNight = $this->medicalStayAmountPerNight($booking);
    $unusedStay = $stayPerNight * $nightsRemaining;
    $usedStay = $stayPerNight * $nightsElapsed;
    $services = (int) $booking->services_subtotal;
    $employerDebtAfter = $days >= 0 ? 0 : max(0, $usedStay + $services);

    return [
      'days'                      => $days,
      'percentage'                => 100,
      'amount'                    => 0,
      'nights_total'              => $totalNights,
      'nights_elapsed'            => $nightsElapsed,
      'nights_remaining'          => $nightsRemaining,
      'basis_amount'              => $unusedStay,
      'is_mid_stay'               => $isMidStay,
      'guest_paid'                => false,
      'medical_used_stay_amount'  => $usedStay,
      'employer_debt_after'       => $employerDebtAfter,
    ];
  }

  private function medicalStayAmountPerNight(Booking $booking): int
  {
    $snapshot = $booking->medical_tariff_snapshot;
    if (is_array($snapshot) && isset($snapshot['stay_total'], $snapshot['nights']) && (int) $snapshot['nights'] > 0) {
      return (int) round((int) $snapshot['stay_total'] / (int) $snapshot['nights']);
    }

    $nights = max(1, (int) $booking->nights);
    $services = (int) $booking->services_subtotal;

    return (int) round(max(0, (int) $booking->total_price - $services) / $nights);
  }
}
