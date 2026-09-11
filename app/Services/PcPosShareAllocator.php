<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PcPosPaymentFailedException;
use App\Models\ProvincePosSettlement;
use App\Support\IranIban;

class PcPosShareAllocator
{
    /**
     * Bank rule: sum(Shares.amount) must equal AM exactly.
     *
     * @return list<array{amount: string, iban: string, trackingId: string, label: string, kind: string, percentage: ?string}>
     */
    public function allocate(ProvincePosSettlement $settlement, int $amount, int $serviceFeeAmount): array
    {
        if ($amount <= 0) {
            throw new PcPosPaymentFailedException('مبلغ تراکنش پوز معتبر نیست.');
        }

        $settlement->loadMissing('accounts', 'province');
        $accounts = $settlement->orderedAccounts();
        if ($accounts->isEmpty()) {
            throw new PcPosPaymentFailedException(
                'برای استان «' . ($settlement->province?->name ?? '') . '» حساب باقیمانده تسهیم وجه تعریف نشده است.'
            );
        }

        $percentTotal = round((float) $accounts->sum('percentage'), 2);
        if (abs($percentTotal - 100.0) > 0.009) {
            throw new PcPosPaymentFailedException(
                'مجموع درصد حساب‌های باقیمانده استان باید دقیقاً ۱۰۰ باشد.'
            );
        }

        $serviceFeeAmount = max(0, min($amount, $serviceFeeAmount));
        $remainder = $amount - $serviceFeeAmount;
        $shares = [];

        if ($serviceFeeAmount > 0) {
            $iban = IranIban::normalize($settlement->service_fee_iban);
            if (! IranIban::isValid($iban)) {
                throw new PcPosPaymentFailedException('شماره شبا حساب حق سرویس نامعتبر است.');
            }

            $shares[] = $this->share(
                $serviceFeeAmount,
                $iban,
                trim((string) $settlement->service_fee_label) ?: 'حق سرویس',
                'service_fee',
                null,
            );
        }

        $allocated = 0;
        $lastIndex = $accounts->count() - 1;
        foreach ($accounts->values() as $index => $account) {
            $iban = IranIban::normalize($account->iban);
            if (! IranIban::isValid($iban)) {
                throw new PcPosPaymentFailedException(
                    'شماره شبا حساب «' . $account->label . '» نامعتبر است.'
                );
            }

            if ($remainder <= 0) {
                break;
            }

            $part = $index === $lastIndex
                ? ($remainder - $allocated)
                : (int) floor($remainder * ((float) $account->percentage) / 100);
            $allocated += $part;

            if ($part <= 0) {
                continue;
            }

            $shares[] = $this->share(
                $part,
                $iban,
                (string) $account->label,
                'remainder',
                number_format((float) $account->percentage, 2, '.', ''),
            );
        }

        if ($shares === []) {
            throw new PcPosPaymentFailedException('امکان ساخت تسهیم وجه برای این مبلغ وجود ندارد.');
        }

        $sum = array_sum(array_map(fn (array $share) => (int) $share['amount'], $shares));
        if ($sum !== $amount) {
            throw new PcPosPaymentFailedException('جمع مبالغ تسهیم با مبلغ تراکنش برابر نیست.');
        }

        return $shares;
    }

    /**
     * @return array{amount: string, iban: string, trackingId: string, label: string, kind: string, percentage: ?string}
     */
    private function share(int $amount, string $iban, string $label, string $kind, ?string $percentage): array
    {
        return [
            'amount' => (string) $amount,
            'iban' => $iban,
            'trackingId' => $this->trackingId(),
            'label' => $label,
            'kind' => $kind,
            'percentage' => $percentage,
        ];
    }

    private function trackingId(): string
    {
        return now()->format('ymdHis') . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $shares
     * @return list<array{amount: string, iban: string, trackingId: string}>
     */
    public function toAgentShares(array $shares): array
    {
        return array_map(fn (array $share) => [
            'amount' => (string) $share['amount'],
            'iban' => (string) $share['iban'],
            'trackingId' => (string) $share['trackingId'],
        ], $shares);
    }
}
