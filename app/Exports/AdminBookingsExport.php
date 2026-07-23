<?php

namespace App\Exports;

use App\Models\Booking;
use App\Support\AdminBookingFilter;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Morilog\Jalali\Jalalian;

class AdminBookingsExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int>|null  $scopedAccommodationIds
     */
    public function __construct(
        private array $filters = [],
        private ?array $scopedAccommodationIds = null,
    ) {}

    public function query()
    {
        $query = Booking::with(['user', 'createdBy', 'accommodation', 'roomType', 'bookingRooms.room', 'bookingRooms.roomType']);

        return AdminBookingFilter::make($this->filters, $this->scopedAccommodationIds)
            ->apply($query, withSort: false)
            ->latest();
    }

    public function headings(): array
    {
        return [
            'کد رزرو',
            'مهمان اصلی',
            'موبایل مهمان اصلی',
            'رزرو کننده',
            'اقامتگاه',
            'نوع اتاق',
            'نام اتاق',
            'گروه ایثارگری مهمان',
            'نوع رزرو',
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
            $booking->bookerName(),
            $booking->bookerMobile(),
            $booking->reserverName(),
            $booking->accommodation->name ?? '—',
            $booking->roomTypeNamesSummary(),
            $booking->physicalRoomNamesDisplay(),
            $booking->veteranDiscountLabel(),
            $booking->bookingTypeLabel(),
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
