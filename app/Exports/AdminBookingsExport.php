<?php

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Morilog\Jalali\Jalalian;

class AdminBookingsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private array $filters = [])
    {
    }

    public function query()
    {
        $query = Booking::with(['user', 'accommodation', 'roomType'])->latest();

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $s = $this->filters['search'];
            $query->where(function ($w) use ($s) {
                $w->where('tracking_code', 'like', "%{$s}%")
                  ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%"))
                  ->orWhereHas('accommodation', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            });
        }

        if (!empty($this->filters['accommodation_id'])) {
            $query->where('accommodation_id', $this->filters['accommodation_id']);
        }

        if (!empty($this->filters['city_id'])) {
            $query->whereHas('accommodation', fn($q) => $q->where('city_id', $this->filters['city_id']));
        }

        if (!empty($this->filters['check_in_from']) && ($d = $this->toGregorian($this->filters['check_in_from']))) {
            $query->whereDate('check_in', '>=', $d);
        }
        if (!empty($this->filters['check_in_to']) && ($d = $this->toGregorian($this->filters['check_in_to']))) {
            $query->whereDate('check_in', '<=', $d);
        }
        if (!empty($this->filters['check_out_from']) && ($d = $this->toGregorian($this->filters['check_out_from']))) {
            $query->whereDate('check_out', '>=', $d);
        }
        if (!empty($this->filters['check_out_to']) && ($d = $this->toGregorian($this->filters['check_out_to']))) {
            $query->whereDate('check_out', '<=', $d);
        }

        if (!empty($this->filters['nights_min'])) {
            $query->where('nights', '>=', (int)$this->filters['nights_min']);
        }
        if (!empty($this->filters['nights_max'])) {
            $query->where('nights', '<=', (int)$this->filters['nights_max']);
        }

        if (!empty($this->filters['price_min'])) {
            $query->where('total_price', '>=', (int)$this->filters['price_min']);
        }
        if (!empty($this->filters['price_max'])) {
            $query->where('total_price', '<=', (int)$this->filters['price_max']);
        }

        if (!empty($this->filters['guests_min'])) {
            $query->where('guests', '>=', (int)$this->filters['guests_min']);
        }

        if (!empty($this->filters['has_discount'])) {
            $query->where('discount_percentage', '>', 0);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'کد رزرو',
            'کاربر',
            'موبایل',
            'اقامتگاه',
            'اتاق',
            'ورود',
            'خروج',
            'شب',
            'مبلغ',
            'وضعیت',
            'تاریخ ثبت',
        ];
    }

    public function map($booking): array
    {
        return [
            $booking->tracking_code,
            $booking->user->name ?? '—',
            $booking->user->mobile ?? '—',
            $booking->accommodation->name ?? '—',
            $booking->roomType->name ?? '—',
            $this->formatDate($booking->check_in),
            $this->formatDate($booking->check_out),
            $booking->nights,
            $booking->total_price,
            $booking->statusLabel(),
            $this->formatDateTime($booking->created_at),
        ];
    }

    private function formatDate($value): string
    {
        if (!$value) {
            return '—';
        }

        try {
            return Jalalian::fromCarbon(Carbon::parse($value))->format('Y/m/d');
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function toGregorian(?string $jalali): ?string
    {
        if (!$jalali) return null;
        try {
            $normalized = strtr(trim($jalali), [
                '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
                '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            ]);
            return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}