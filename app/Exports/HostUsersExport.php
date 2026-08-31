<?php

namespace App\Exports;

use App\Models\User;
use App\Support\HostUserFilter;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Morilog\Jalali\Jalalian;

class HostUsersExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int>  $accommodationIds
     */
    public function __construct(
        private array $filters = [],
        private array $accommodationIds = [],
    ) {}

    public function query()
    {
        return HostUserFilter::make($this->filters, $this->accommodationIds)
            ->apply(User::query());
    }

    public function headings(): array
    {
        return [
            'نام',
            'نوع کاربر',
            'موبایل',
            'شناسه',
            'محل اقامت',
            'گروه ایثارگری',
            'تعداد رزرو',
            'آخرین رزرو',
        ];
    }

    public function map($user): array
    {
        return [
            $user->name ?? '—',
            $user->roleBadgeLabel(),
            $user->mobile,
            $user->identityNumber() ?? '—',
            $user->residenceLocationLabel() ?? '—',
            $user->veteranLabel(),
            $user->host_bookings_count ?? 0,
            $this->formatDateTime($user->last_booking_at),
        ];
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
