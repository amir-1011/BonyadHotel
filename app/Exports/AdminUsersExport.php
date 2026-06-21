<?php

namespace App\Exports;

use App\Models\User;
use App\Support\AdminUserFilter;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Morilog\Jalali\Jalalian;

class AdminUsersExport implements FromQuery, WithHeadings, WithMapping
{
    /** @param  array<string, mixed>  $filters */
    public function __construct(private array $filters = []) {}

    public function query()
    {
        $query = User::with('roles');

        return AdminUserFilter::make($this->filters)
            ->apply($query, withSort: false)
            ->latest();
    }

    public function headings(): array
    {
        return [
            'شناسه',
            'نام',
            'موبایل',
            'کد ملی',
            'نقش',
            'گروه ایثارگری',
            'تخفیف',
            'تاریخ ثبت',
            'وضعیت',
        ];
    }

    public function map($user): array
    {
        $roles = $user->roles->pluck('name')->all();

        return [
            $user->id,
            $user->name ?? '—',
            $user->mobile,
            $user->national_id ?? '—',
            $roles ? implode('، ', $roles) : 'guest',
            $user->veteranLabel(),
            $user->discount_percentage > 0 ? $user->discount_percentage.'%' : '—',
            $this->formatDateTime($user->created_at),
            $user->mobile_verified_at ? 'فعال' : 'غیرفعال',
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
