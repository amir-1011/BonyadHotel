<?php

namespace App\Exports;

use App\Models\PlatformCommissionEntry;
use App\Support\PlatformCommissionEntryFilter;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Morilog\Jalali\Jalalian;

class AdminCommissionWalletExport implements FromQuery, WithHeadings, WithMapping
{
    /** @param  array<string, mixed>  $filters */
    public function __construct(private array $filters = []) {}

    public function query()
    {
        $query = PlatformCommissionEntry::query()
            ->with(['booking.user', 'accommodation.city', 'serviceCatalog', 'createdBy']);

        return PlatformCommissionEntryFilter::make($this->filters)->apply($query);
    }

    public function headings(): array
    {
        return [
            'شناسه',
            'تاریخ ثبت',
            'نوع رکورد',
            'دلیل',
            'دسته',
            'نام خدمت',
            'کد رزرو',
            'شناسه رزرو',
            'اقامتگاه',
            'شهر',
            'مهمان اصلی',
            'موبایل',
            'منبع رزرو',
            'وضعیت رزرو',
            'تاریخ ورود',
            'تاریخ خروج',
            'مبلغ تراکنش',
            'درصد کارمزد',
            'سقف کارمزد',
            'مبلغ کارمزد',
            'توضیح',
            'ثبت توسط',
        ];
    }

    /** @param  PlatformCommissionEntry  $entry */
    public function map($entry): array
    {
        $meta = $entry->meta ?? [];
        $booking = $entry->booking;

        return [
            $entry->id,
            $this->formatDateTime($entry->created_at),
            $entry->entryTypeLabel(),
            $entry->reasonLabel(),
            $entry->categoryLabel(),
            $entry->service_name ?? ($meta['description'] ?? '—'),
            $meta['tracking_code'] ?? $booking?->tracking_code ?? '—',
            $entry->booking_id ?? '—',
            $meta['accommodation_name'] ?? $entry->accommodation?->name ?? '—',
            $entry->accommodation?->city?->name ?? '—',
            $meta['booker_name'] ?? $booking?->bookerName() ?? '—',
            $meta['booker_mobile'] ?? $booking?->bookerMobile() ?? '—',
            $entry->bookingSourceLabel(),
            $booking?->statusLabel() ?? ($meta['booking_status'] ?? '—'),
            $this->formatDate($meta['check_in'] ?? $booking?->check_in),
            $this->formatDate($meta['check_out'] ?? $booking?->check_out),
            $entry->transaction_amount,
            $entry->commission_percentage,
            $entry->commission_cap,
            $entry->commission_amount,
            $entry->fullExplanation(),
            $entry->createdBy?->name ?? '—',
        ];
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '—';
        }

        try {
            return Jalalian::fromCarbon(Carbon::parse($value))->format('Y/m/d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function formatDateTime($value): string
    {
        if (!$value) {
            return '—';
        }

        try {
            return Jalalian::fromCarbon(Carbon::parse($value))->format('Y/m/d H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
