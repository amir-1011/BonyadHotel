<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\MedicalAccommodationContract;
use App\Models\MedicalAccommodationSetting;
use App\Models\MedicalAccommodationTariff;
use Carbon\Carbon;
use RuntimeException;

class MedicalAccommodationBillingService
{
    public function __construct(
        private readonly MedicalAccommodationProvisioner $provisioner,
        private readonly MedicalAccommodationPricingService $pricing,
        private readonly RefundPolicyService $refundPolicy,
    ) {}

    public function settingFor(Accommodation $accommodation): MedicalAccommodationSetting
    {
        return $this->provisioner->seedForAccommodation($accommodation);
    }

    public function activeContracts(Accommodation $accommodation, ?string $checkIn = null, ?string $checkOut = null)
    {
        $this->provisioner->seedForAccommodation($accommodation);

        $query = MedicalAccommodationContract::query()
            ->forAccommodation($accommodation->id)
            ->active()
            ->with('employer')
            ->ordered();

        $contracts = $query->get();

        if ($checkIn && $checkOut) {
            return $contracts
                ->filter(fn (MedicalAccommodationContract $contract) => $contract->coversStay($checkIn, $checkOut))
                ->values();
        }

        return $contracts;
    }

    public function resolveContract(Accommodation $accommodation, ?int $contractId, ?string $checkIn = null, ?string $checkOut = null): MedicalAccommodationContract
    {
        $contracts = $this->activeContracts($accommodation, $checkIn, $checkOut);

        if ($contractId) {
            $match = $contracts->firstWhere('id', $contractId)
                ?? MedicalAccommodationContract::query()
                    ->forAccommodation($accommodation->id)
                    ->active()
                    ->whereKey($contractId)
                    ->first();
            if ($match) {
                if ($checkIn && $checkOut && !$match->coversStay($checkIn, $checkOut)) {
                    throw new RuntimeException('تاریخ اقامت خارج از بازه قرارداد «'.$match->contract_number.'» است.');
                }

                return $match;
            }
        }

        $contract = $contracts->first();
        if (!$contract) {
            throw new RuntimeException('برای این اقامتگاه قرارداد فعال اسکان درمانی تعریف نشده است. ابتدا از بخش اسکان درمانی قرارداد را ثبت کنید.');
        }

        if ($checkIn && $checkOut && !$contract->coversStay($checkIn, $checkOut)) {
            throw new RuntimeException('تاریخ اقامت خارج از بازه قراردادهای اسکان درمانی این اقامتگاه است.');
        }

        return $contract;
    }

    public function activeTariffs(Accommodation $accommodation, ?int $contractId = null)
    {
        $this->provisioner->seedForAccommodation($accommodation);

        $query = MedicalAccommodationTariff::query()
            ->forAccommodation($accommodation->id)
            ->active()
            ->ordered();

        if ($contractId) {
            $query->forContract($contractId);
        }

        return $query->get();
    }

    public function resolveTariff(Accommodation $accommodation, ?int $tariffId, ?int $contractId = null): MedicalAccommodationTariff
    {
        $this->provisioner->seedForAccommodation($accommodation);

        $query = MedicalAccommodationTariff::query()
            ->forAccommodation($accommodation->id)
            ->active();

        if ($contractId) {
            $query->forContract($contractId);
        }

        if ($tariffId) {
            $tariff = (clone $query)->whereKey($tariffId)->first();
            if ($tariff) {
                return $tariff;
            }

            throw new RuntimeException('تعرفه انتخاب‌شده به قرارداد اسکان درمانی انتخاب‌شده تعلق ندارد.');
        }

        $tariff = $query->ordered()->first();
        if (!$tariff) {
            throw new RuntimeException('برای این قرارداد تعرفه فعال اسکان درمانی تعریف نشده است. ابتدا از بخش اسکان درمانی تعرفه را ثبت کنید.');
        }

        return $tariff;
    }

    /**
     * @return array<string, mixed>
     */
    public function assertReadyForBooking(
        Accommodation $accommodation,
        string $checkIn,
        string $checkOut,
        int $guests,
        ?int $tariffId,
        ?int $contractId = null,
    ): array {
        $setting = $this->settingFor($accommodation);
        $contract = $this->resolveContract($accommodation, $contractId, $checkIn, $checkOut);

        $employerId = (int) ($contract->program_employer_id ?: $setting->program_employer_id);
        if (!$employerId) {
            throw new RuntimeException('کارفرمای اسکان درمانی (بیمه دی) در قرارداد انتخاب‌شده مشخص نشده است.');
        }

        $nights = Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
        if ($setting->require_overnight && $nights < 1) {
            throw new RuntimeException('اسکان درمانی فقط برای اقامت شبانه قابل ثبت است.');
        }

        $tariff = $this->resolveTariff($accommodation, $tariffId, $contract->id);
        $companions = $this->pricing->companionCountFromOccupancy($guests);
        $quote = $this->pricing->quote($tariff, $nights, $companions);
        $quote['contract_id'] = $contract->id;
        $quote['contract_number'] = $contract->contract_number;

        return [
            'setting'     => $setting,
            'contract'    => $contract,
            'tariff'      => $tariff,
            'quote'       => $quote,
            'employer_id' => $employerId,
        ];
    }

    public function assertStayWithinContract(MedicalAccommodationContract|MedicalAccommodationSetting $contract, string $checkIn, string $checkOut): void
    {
        if ($contract instanceof MedicalAccommodationSetting) {
            if ($contract->contract_starts_on && Carbon::parse($checkIn)->lt($contract->contract_starts_on->copy()->startOfDay())) {
                throw new RuntimeException('تاریخ ورود خارج از بازه قرارداد اسکان درمانی این اقامتگاه است.');
            }

            if ($contract->contract_ends_on && Carbon::parse($checkOut)->gt($contract->contract_ends_on->copy()->addDay()->startOfDay())) {
                throw new RuntimeException('تاریخ خروج خارج از بازه قرارداد اسکان درمانی این اقامتگاه است.');
            }

            return;
        }

        if (!$contract->coversStay($checkIn, $checkOut)) {
            throw new RuntimeException('تاریخ اقامت خارج از بازه قرارداد «'.$contract->contract_number.'» است.');
        }
    }

    public function assertCompanionLimit(Booking $booking, ?int $guests = null, int $extraGuests = 0): void
    {
        if (!$booking->isMedicalAccommodation()) {
            return;
        }

        $this->pricing->quoteForBooking(
            $booking,
            max(1, (int) $booking->nights),
            $this->pricing->companionCountFromOccupancy(
                $guests ?? (int) $booking->guests,
                $guests === null ? (int) $booking->extra_guests : $extraGuests,
            ),
        );
    }

    public function syncEmployerDebt(Booking $booking, ?int $amount = null): void
    {
        if (!$booking->isMedicalAccommodation()) {
            return;
        }

        $booking->update([
            'employer_debt_amount' => max(0, $amount ?? (int) $booking->total_price),
        ]);
    }

    public function applyCancellationToEmployerDebt(Booking $booking): void
    {
        if (!$booking->isMedicalAccommodation()) {
            return;
        }

        $preview = $this->refundPolicy->previewForBooking($booking);

        $booking->update([
            'employer_debt_amount' => max(0, (int) ($preview['employer_debt_after'] ?? 0)),
        ]);
    }
}
