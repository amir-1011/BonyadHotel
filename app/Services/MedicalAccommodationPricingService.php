<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\MedicalAccommodationTariff;
use RuntimeException;

class MedicalAccommodationPricingService
{
    /**
     * @param  MedicalAccommodationTariff|array<string, mixed>  $tariff
     * @return array{
     *   id:?int, key:?string, label:string, nights:int, companion_count:int,
     *   billed_companions:int, nightly_rate:int, companion_nightly_rate:int,
     *   companions_included:int, max_companions:int,
     *   patient_total:int, companion_total:int, stay_total:int
     * }
     */
    public function quote(MedicalAccommodationTariff|array $tariff, int $nights, int $companionCount): array
    {
        $snapshot = $tariff instanceof MedicalAccommodationTariff
            ? $tariff->toQuoteSnapshot()
            : $tariff;

        $nights = max(0, $nights);
        if ($nights < 1) {
            throw new RuntimeException('اسکان درمانی فقط برای اقامت شبانه قابل ثبت است؛ رزرو روزانه تحت پوشش بیمه دی نیست.');
        }

        $companionCount = max(0, $companionCount);
        $maxCompanions = max(0, (int) ($snapshot['max_companions'] ?? 0));
        $included = max(0, (int) ($snapshot['companions_included'] ?? 0));
        $label = (string) ($snapshot['label'] ?? 'تعرفه اسکان درمانی');

        if ($companionCount > $maxCompanions) {
            throw new RuntimeException(
                'تعرفه «' . $label . '» حداکثر ' . $maxCompanions . ' همراه را می‌پذیرد.'
            );
        }

        $nightlyRate = max(0, (int) ($snapshot['nightly_rate'] ?? 0));
        $companionRate = max(0, (int) ($snapshot['companion_nightly_rate'] ?? 0));
        $billedCompanions = max(0, $companionCount - $included);
        $patientTotal = $nightlyRate * $nights;
        $companionTotal = $billedCompanions * $companionRate * $nights;

        return [
            'id'                     => isset($snapshot['id']) ? (int) $snapshot['id'] : null,
            'key'                    => $snapshot['key'] ?? null,
            'label'                  => $label,
            'nights'                 => $nights,
            'companion_count'        => $companionCount,
            'billed_companions'      => $billedCompanions,
            'nightly_rate'           => $nightlyRate,
            'companion_nightly_rate' => $companionRate,
            'companions_included'    => $included,
            'max_companions'         => $maxCompanions,
            'patient_total'          => $patientTotal,
            'companion_total'        => $companionTotal,
            'stay_total'             => $patientTotal + $companionTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public function overlayQuote(array $pricing, array $quote): array
    {
        $servicesSubtotal = (int) ($pricing['services_subtotal'] ?? 0);
        $servicesDiscount = (int) ($pricing['services_discount_amount'] ?? 0);
        $stayTotal = (int) $quote['stay_total'];
        $subtotal = $stayTotal + $servicesSubtotal;
        $totalDiscount = $servicesDiscount;
        $totalPrice = max(0, $subtotal - $totalDiscount);

        $pricing['room_subtotal'] = (int) $quote['patient_total'];
        $pricing['extra_guests_total'] = (int) $quote['companion_total'];
        $pricing['children_discount_amount'] = 0;
        $pricing['veteran_accommodation_discount_amount'] = 0;
        $pricing['manual_accommodation_discount_amount'] = 0;
        $pricing['veteran_discount_nights'] = 0;
        $pricing['veteran_accommodation_group_usage'] = [];
        $pricing['accommodation_discount_breakdown'] = [];
        $pricing['accommodation_discount_percentage'] = 0;
        $pricing['discount_percentage'] = $subtotal > 0 ? (int) round($totalDiscount / $subtotal * 100) : 0;
        $pricing['discount_amount'] = $totalDiscount;
        $pricing['subtotal_before_discount'] = $subtotal;
        $pricing['total_price'] = $totalPrice;
        $pricing['is_medical_accommodation'] = true;
        $pricing['medical'] = $quote;
        $pricing['guest_payable'] = 0;
        $pricing['employer_debt'] = $totalPrice;

        return $pricing;
    }

    public function companionCountFromOccupancy(int $guests, int $extraGuests = 0): int
    {
        return max(0, max(1, $guests + $extraGuests) - 1);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quoteForBooking(Booking $booking, ?int $nights = null, ?int $companionCount = null): ?array
    {
        $snapshot = $booking->medical_tariff_snapshot;
        if (!is_array($snapshot) || $snapshot === []) {
            $tariff = $booking->medicalTariff;
            if (!$tariff) {
                return null;
            }
            $snapshot = $tariff->toQuoteSnapshot();
        }

        $nights ??= max(1, (int) $booking->nights);
        $companionCount ??= $this->companionCountFromOccupancy(
            (int) $booking->guests,
            (int) $booking->extra_guests,
        );

        $quote = $this->quote($snapshot, $nights, $companionCount);

        if (!empty($snapshot['contract_id'])) {
            $quote['contract_id'] = (int) $snapshot['contract_id'];
        } elseif ($booking->medical_contract_id) {
            $quote['contract_id'] = (int) $booking->medical_contract_id;
        }

        $contractNumber = $snapshot['contract_number'] ?? $booking->medicalContractNumber();
        if ($contractNumber) {
            $quote['contract_number'] = (string) $contractNumber;
        }

        return $quote;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array<string, mixed>
     */
    public function overlayBooking(array $pricing, Booking $booking): array
    {
        $quote = $this->quoteForBooking($booking, (int) ($pricing['nights'] ?? $booking->nights));

        if (!$quote) {
            $pricing['is_medical_accommodation'] = true;
            $pricing['guest_payable'] = 0;
            $pricing['employer_debt'] = (int) ($pricing['total_price'] ?? $booking->total_price);

            return $pricing;
        }

        return $this->overlayQuote($pricing, $quote);
    }
}
