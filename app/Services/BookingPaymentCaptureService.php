<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingPaymentRecord;
use App\Models\PosTerminal;
use App\Models\User;
use App\Support\JalaliDateTimeInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookingPaymentCaptureService
{
    private const VAT_META_PATTERN = '/\x{200B}bnb-vat:(\d+)\z/u';

    public function __construct(
        private readonly ProgramDocumentService $documents,
    ) {}

    public static function vatAmountFromPercent(int $baseAmount, int $percent): int
    {
        $percent = min(20, max(0, $percent));
        if ($percent <= 0 || $baseAmount <= 0) {
            return 0;
        }

        return (int) round($baseAmount * $percent / 100);
    }

    public function composePriceAdjustmentReason(?string $manualReason, int $vatPercent, int $vatBaseAmount): ?string
    {
        $manualReason = trim((string) $manualReason);
        $vatAmount = self::vatAmountFromPercent($vatBaseAmount, $vatPercent);
        $parts = [];

        if ($manualReason !== '') {
            $parts[] = $manualReason;
        }

        if ($vatAmount > 0 && $vatPercent > 0) {
            $parts[] = 'بعلاوه ' . $vatPercent . '٪ مالیات بر ارزش افزوده';
        }

        if ($parts === []) {
            return null;
        }

        $text = implode(' — ', $parts);

        if ($vatAmount > 0) {
            $text .= "\u{200B}bnb-vat:" . $vatAmount;
        }

        return $text;
    }

    public static function displayPriceAdjustmentReason(?string $stored): ?string
    {
        if ($stored === null || trim($stored) === '') {
            return null;
        }

        $stripped = preg_replace(self::VAT_META_PATTERN, '', trim($stored));

        return $stripped !== '' ? $stripped : null;
    }

    public static function vatAmountFromStoredReason(?string $stored): int
    {
        if ($stored === null || ! preg_match(self::VAT_META_PATTERN, trim($stored), $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    public function totalRecordedVatForBooking(Booking $booking): int
    {
        $booking->loadMissing('paymentRecords');

        return (int) $booking->paymentRecords
            ->sum(fn (BookingPaymentRecord $record) => self::vatAmountFromStoredReason($record->price_adjustment_reason));
    }

    /**
     * @param  array<string, mixed>  $capture
     */
    public function validateCapture(array $capture, bool $requireTerminal = true): array
    {
        $rules = [
            'card_last_four' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'transaction_tracking' => ['nullable', 'string', 'max:120'],
            'payment_date_jalali' => ['required', 'string', 'max:20'],
            'payment_time' => ['required', 'string', 'max:10'],
            'pos_terminal_id' => $requireTerminal
                ? ['required', 'integer', 'exists:pos_terminals,id']
                : ['nullable', 'integer', 'exists:pos_terminals,id'],
            'price_adjustment_reason' => ['nullable', 'string', 'max:500'],
        ];

        $validator = Validator::make($capture, $rules, [], [
            'card_last_four' => '۴ رقم آخر کارت',
            'transaction_tracking' => 'شماره پیگیری',
            'payment_date_jalali' => 'تاریخ پرداخت',
            'payment_time' => 'ساعت پرداخت',
            'pos_terminal_id' => 'ترمینال',
            'price_adjustment_reason' => 'توضیحات تغییر مبلغ',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $data = $validator->validated();
        $card = trim((string) ($data['card_last_four'] ?? ''));
        $tracking = trim((string) ($data['transaction_tracking'] ?? ''));

        if ($card === '' && $tracking === '') {
            $fromPosAgent = ! empty($capture['pos_agent_approved']);
            if ($fromPosAgent) {
                $tracking = 'POS';
            } else {
                throw ValidationException::withMessages([
                    'transaction_tracking' => 'حداقل یکی از «۴ رقم آخر کارت» یا «شماره پیگیری» باید ثبت شود.',
                ]);
            }
        }

        try {
            $paymentAt = JalaliDateTimeInput::toCarbon(
                (string) $data['payment_date_jalali'],
                (string) $data['payment_time'],
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'payment_time' => $e->getMessage(),
            ]);
        }

        $data['card_last_four'] = $card !== '' ? $card : null;
        $data['transaction_tracking'] = $tracking !== '' ? $tracking : null;
        $data['payment_at'] = $paymentAt;
        $data['price_adjustment_reason'] = trim((string) ($data['price_adjustment_reason'] ?? '')) ?: null;

        return $data;
    }

    /**
     * @param  array<int, UploadedFile|null>  $uploads
     * @return array<int, string>
     */
    public function storeDocumentUploads(array $uploads): array
    {
        $files = array_values(array_filter($uploads, fn ($file) => $file instanceof UploadedFile));

        if ($files === []) {
            return [];
        }

        return $this->documents->storeMany($files, 'booking-payment-documents');
    }

    /**
     * @param  array<string, mixed>  $capture
     */
    public function record(
        Booking $booking,
        int $amountDelta,
        array $capture,
        string $context,
        ?string $action,
        ?User $recordedBy,
        array $documentUploads = [],
    ): BookingPaymentRecord {
        $fromPosAgent = ! empty($capture['pos_agent_approved']);
        $hasTerminal = isset($capture['pos_terminal_id']) && (int) $capture['pos_terminal_id'] > 0;
        $validated = $this->validateCapture($capture, requireTerminal: ! $fromPosAgent);

        $terminal = null;
        if ($hasTerminal) {
            $terminal = PosTerminal::query()->findOrFail((int) $validated['pos_terminal_id']);
            $booking->loadMissing('accommodation.city.province', 'accommodation.county.province');
            $provinceId = $booking->accommodation?->resolvedProvince()?->id;

            if ($provinceId && (int) $terminal->province_id !== (int) $provinceId) {
                throw ValidationException::withMessages([
                    'pos_terminal_id' => 'ترمینال انتخاب‌شده با استان اقامتگاه هم‌خوانی ندارد.',
                ]);
            }
        }

        $documentPaths = $this->storeDocumentUploads($documentUploads);
        if (! empty($capture['document_paths']) && is_array($capture['document_paths'])) {
            $documentPaths = array_values(array_unique(array_merge(
                $documentPaths,
                array_filter($capture['document_paths'], 'is_string'),
            )));
        }

        $amount = (int) $booking->total_price;
        $posResponse = is_array($capture['pos_response'] ?? null) ? $capture['pos_response'] : null;

        return DB::transaction(function () use (
            $booking,
            $amount,
            $amountDelta,
            $validated,
            $terminal,
            $documentPaths,
            $posResponse,
            $context,
            $action,
            $recordedBy,
        ): BookingPaymentRecord {
            return BookingPaymentRecord::create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'amount_delta' => $amountDelta,
                'price_adjustment_reason' => $validated['price_adjustment_reason'],
                'card_last_four' => $validated['card_last_four'],
                'transaction_tracking' => $validated['transaction_tracking'],
                'payment_at' => $validated['payment_at'],
                'pos_terminal_id' => $terminal?->id,
                'document_paths' => $documentPaths !== [] ? $documentPaths : null,
                'pos_response' => $posResponse,
                'context' => $context,
                'action' => $action,
                'recorded_by' => $recordedBy?->id,
            ]);
        });
    }

    public function recordOptionalAdjustmentNote(
        Booking $booking,
        int $amountDelta,
        ?string $reason,
        string $context,
        ?string $action,
        ?User $recordedBy,
    ): ?BookingPaymentRecord {
        $reason = trim((string) $reason);
        if ($reason === '' && $amountDelta === 0) {
            return null;
        }

        return BookingPaymentRecord::create([
            'booking_id' => $booking->id,
            'amount' => (int) $booking->total_price,
            'amount_delta' => $amountDelta,
            'price_adjustment_reason' => $reason !== '' ? $reason : null,
            'payment_at' => now(),
            'context' => $context,
            'action' => $action,
            'recorded_by' => $recordedBy?->id,
        ]);
    }

    /**
     * @return array<int, array{id:int, label:string}>
     */
    public function terminalsForProvince(?int $provinceId): array
    {
        if (!$provinceId) {
            return [];
        }

        return PosTerminal::query()
            ->where('province_id', $provinceId)
            ->where('is_active', true)
            ->orderBy('terminal_number')
            ->get()
            ->map(fn (PosTerminal $terminal) => [
                'id' => $terminal->id,
                'label' => $terminal->displayLabel(),
            ])
            ->all();
    }
}
