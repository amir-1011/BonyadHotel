<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Accommodation;
use App\Support\JalaliDateTimeInput;

class PcPosPurchaseResult
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $requestPayload
     */
    public function __construct(
        public readonly bool $httpOk,
        public readonly int $httpStatus,
        public readonly int $respCode,
        public readonly string $respMsg,
        public readonly array $tlv,
        public readonly array $raw,
        public readonly array $requestPayload,
        public readonly ?string $error = null,
    ) {}

    public static function connectionFailure(string $message, array $requestPayload = []): self
    {
        return new self(
            httpOk: false,
            httpStatus: 0,
            respCode: -1,
            respMsg: $message,
            tlv: [],
            raw: [],
            requestPayload: $requestPayload,
            error: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $requestPayload
     */
    public static function fromAgentResponse(int $httpStatus, array $json, array $requestPayload = []): self
    {
        $tlv = is_array($json['resp_tlv'] ?? null) ? $json['resp_tlv'] : [];

        return new self(
            httpOk: $httpStatus >= 200 && $httpStatus < 300,
            httpStatus: $httpStatus,
            respCode: (int) ($json['resp_code'] ?? -1),
            respMsg: trim((string) ($json['resp_msg'] ?? '')),
            tlv: $tlv,
            raw: $json,
            requestPayload: $requestPayload,
        );
    }

    public function rs(): string
    {
        return trim((string) ($this->tlv['RS'] ?? ''));
    }

    public function approved(): bool
    {
        return $this->httpOk && $this->rs() === '00';
    }

    public function rrn(): string
    {
        return trim((string) ($this->tlv['RN'] ?? ''));
    }

    public function terminalNumber(): string
    {
        return trim((string) ($this->tlv['TM'] ?? ''));
    }

    public function panMasked(): string
    {
        return trim((string) ($this->tlv['PN'] ?? ''));
    }

    public function cardLastFour(): ?string
    {
        $pan = preg_replace('/\D/', '', $this->panMasked()) ?? '';
        if (strlen($pan) < 4) {
            return null;
        }

        return substr($pan, -4);
    }

    public function transactionTime(): string
    {
        return trim((string) ($this->tlv['TI'] ?? ''));
    }

    public function userMessage(): string
    {
        if ($this->approved()) {
            return 'پرداخت روی دستگاه پوز موفق بود.';
        }

        if ($this->error) {
            return $this->error;
        }

        if ($this->respCode < 0 || $this->tlv === []) {
            $msg = $this->respMsg !== '' ? $this->respMsg : 'دستگاه پوز پاسخ نداد.';

            return 'پرداخت پوز انجام نشد. ' . $msg;
        }

        $rs = $this->rs();

        return match ($rs) {
            '55' => 'رمز کارت نادرست است. دوباره روی کارتخوان تلاش کنید.',
            '51' => 'موجودی کارت کافی نیست.',
            '54' => 'کارت منقضی شده است.',
            '41', '43' => 'کارت مسدود یا اعلام سرقت شده است.',
            '57' => 'تراکنش برای این کارت مجاز نیست.',
            '61' => 'مبلغ از سقف مجاز کارت بیشتر است.',
            default => $rs !== ''
                ? 'پرداخت روی دستگاه پوز ناموفق بود. (کد پاسخ: ' . $rs . ')'
                : 'پرداخت روی دستگاه پوز ناموفق بود.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toPaymentCapture(?Accommodation $accommodation = null): array
    {
        [$jalaliDate, $time] = $this->parsedPaymentDateTime();

        return [
            'card_last_four' => $this->cardLastFour(),
            'transaction_tracking' => $this->rrn() !== '' ? $this->rrn() : ($this->tlv['TR'] ?? null),
            'payment_date_jalali' => $jalaliDate,
            'payment_time' => $time,
            'pos_terminal_id' => $this->matchedPosTerminalId($accommodation),
            'pos_agent_approved' => true,
            'pos_response' => [
                'resp_code' => $this->respCode,
                'resp_msg' => $this->respMsg,
                'resp_tlv' => $this->tlv,
                'request' => $this->requestPayload,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parsedPaymentDateTime(): array
    {
        $ti = $this->transactionTime();
        if (preg_match('/^(\d{4}\/\d{2}\/\d{2})[ T-](\d{2}:\d{2})/', $ti, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return [JalaliDateTimeInput::nowJalaliDate(), JalaliDateTimeInput::nowTime()];
    }

    private function matchedPosTerminalId(?Accommodation $accommodation): ?int
    {
        $number = $this->terminalNumber();
        if ($number === '') {
            return null;
        }

        $query = \App\Models\PosTerminal::query()
            ->where('terminal_number', $number)
            ->where('is_active', true);

        $provinceId = $accommodation?->resolvedProvince()?->id;
        if ($provinceId) {
            $match = (clone $query)->where('province_id', $provinceId)->first();
            if ($match) {
                return (int) $match->id;
            }
        }

        $match = $query->first();

        return $match ? (int) $match->id : null;
    }
}
