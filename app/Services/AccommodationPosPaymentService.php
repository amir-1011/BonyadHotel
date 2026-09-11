<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PcPosPaymentFailedException;
use App\Models\Accommodation;
use App\Models\AccommodationPosMapping;
use App\Models\Booking;
use App\Models\ProvincePosSettlement;

class AccommodationPosPaymentService
{
    public function __construct(
        private readonly PcPosAgentClient $client,
        private readonly PcPosShareAllocator $shareAllocator,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('pcpos.enabled', true);
    }

    public function mappingFor(int $accommodationId): ?AccommodationPosMapping
    {
        return AccommodationPosMapping::query()
            ->where('accommodation_id', $accommodationId)
            ->where('is_active', true)
            ->first();
    }

    public function isReady(?int $accommodationId): bool
    {
        if (! $this->enabled() || ! $accommodationId) {
            return false;
        }

        return $this->mappingFor($accommodationId) !== null;
    }

    /**
     * @return array{pos_charge_enabled: bool, pos_charge_full_amount: bool}
     */
    public function previewMeta(
        ?int $accommodationId,
        ?string $paymentMethod,
        bool $skipPaymentCapture,
        bool $chargeFullAmount,
    ): array {
        $ready = $this->isReady($accommodationId)
            && ! $skipPaymentCapture
            && $paymentMethod === Booking::PAYMENT_CARD_TERMINAL;

        return [
            'pos_charge_enabled' => $ready,
            'pos_charge_full_amount' => $chargeFullAmount,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $existingCapture
     * @return array<string, mixed>|null
     */
    public function chargeOrFail(
        Accommodation $accommodation,
        int $amount,
        ?array $existingCapture = null,
        ?string $paymentMethod = Booking::PAYMENT_CARD_TERMINAL,
        int $serviceFeeAmount = 0,
    ): ?array {
        if ($amount <= 0) {
            return is_array($existingCapture) ? $existingCapture : null;
        }

        if ($paymentMethod !== Booking::PAYMENT_CARD_TERMINAL) {
            return is_array($existingCapture) ? $existingCapture : null;
        }

        if (is_array($existingCapture) && ! empty($existingCapture['pos_agent_approved'])) {
            return $existingCapture;
        }

        if (! $this->isReady($accommodation->id)) {
            return is_array($existingCapture) ? $existingCapture : null;
        }

        $charged = $this->charge($accommodation, $amount, $serviceFeeAmount);
        $result = $charged['result'];

        if (! $result->approved()) {
            throw new PcPosPaymentFailedException($result->userMessage());
        }

        $capture = $result->toPaymentCapture($accommodation->loadMissing('city.province', 'county.province'));
        $capture['pos_response']['shares'] = $charged['shares'];

        if (is_array($existingCapture)) {
            if (! empty($existingCapture['price_adjustment_reason'])) {
                $capture['price_adjustment_reason'] = $existingCapture['price_adjustment_reason'];
            }
            if (! empty($existingCapture['document_paths']) && is_array($existingCapture['document_paths'])) {
                $capture['document_paths'] = $existingCapture['document_paths'];
            }
        }

        return $capture;
    }

    /**
     * @return array{result: PcPosPurchaseResult, shares: list<array<string, mixed>>}
     */
    public function charge(Accommodation $accommodation, int $amount, int $serviceFeeAmount = 0): array
    {
        $mapping = $this->mappingFor((int) $accommodation->id);
        if (! $mapping) {
            throw new PcPosPaymentFailedException('اتصال پوز برای این اقامتگاه تنظیم نشده است.');
        }

        $settlement = $this->settlementForAccommodation($accommodation);
        $shares = $this->shareAllocator->allocate($settlement, $amount, $serviceFeeAmount);

        $result = $this->client->purchase(
            windowsLanIp: $mapping->windows_lan_ip,
            posLanIp: $mapping->pos_lan_ip,
            amount: $amount,
            posPort: $mapping->posPort(),
            agentPort: $mapping->agentPort(),
            shares: $this->shareAllocator->toAgentShares($shares),
        );

        return [
            'result' => $result,
            'shares' => $shares,
        ];
    }

    public function settlementForAccommodation(Accommodation $accommodation): ProvincePosSettlement
    {
        $accommodation->loadMissing('city.province', 'county.province');
        $province = $accommodation->resolvedProvince();
        if (! $province) {
            throw new PcPosPaymentFailedException('استان اقامتگاه برای تسهیم وجه مشخص نیست.');
        }

        $settlement = ProvincePosSettlement::query()
            ->with(['accounts', 'province'])
            ->where('province_id', $province->id)
            ->where('is_active', true)
            ->first();

        if (! $settlement) {
            throw new PcPosPaymentFailedException(
                'تسهیم وجه برای استان «' . $province->name . '» تنظیم نشده است. از بخش تسهیم وجه پوز حساب‌ها را ثبت کنید.'
            );
        }

        return $settlement;
    }
}
