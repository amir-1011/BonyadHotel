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
}