<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Program;
use App\Support\SupportiveServicesReportGroups;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class SupportiveServicesReportService
{
    public function __construct(
        private readonly BookingReceiptBreakdownService $breakdown,
    ) {}

    /**
     * @return array{from: string, to: string}|null
     */
    public function rangeFor(string $period, int $jalaliYear, int $jalaliMonth): ?array
    {
        if ($period === 'all') {
            return null;
        }

        if ($period === 'year') {
            $from = Jalalian::fromFormat('Y/m/d', sprintf('%d/01/01', $jalaliYear))->toCarbon()->startOfDay();
            $to = Jalalian::fromFormat('Y/m/d', sprintf('%d/01/01', $jalaliYear + 1))->toCarbon()->startOfDay();

            return [
                'from' => $from->format('Y-m-d'),
                'to'   => $to->format('Y-m-d'),
            ];
        }

        $month = max(1, min(12, $jalaliMonth));
        $start = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $jalaliYear, $month));
        $from = $start->toCarbon()->startOfDay();
        $to = Jalalian::fromFormat(
            'Y/m/d',
            sprintf('%d/%02d/%02d', $jalaliYear, $month, $start->getMonthDays()),
        )->toCarbon()->addDay()->startOfDay();

        return [
            'from' => $from->format('Y-m-d'),
            'to'   => $to->format('Y-m-d'),
        ];
    }

    public function periodLabel(string $period, int $jalaliYear, int $jalaliMonth): string
    {
        if ($period === 'all') {
            return 'همه تاریخ‌ها';
        }

        if ($period === 'year') {
            return (string) $jalaliYear;
        }

        $month = max(1, min(12, $jalaliMonth));

        return Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $jalaliYear, $month))->format('Y F');
    }

    /**
     * @param  array<int>  $accommodationIds
     * @return array<string, mixed>
     */
    public function build(array $accommodationIds, string $period, int $jalaliYear, int $jalaliMonth): array
    {
        $range = $this->rangeFor($period, $jalaliYear, $jalaliMonth);

        if ($accommodationIds === []) {
            return $this->emptyReport($range, $period, $jalaliYear, $jalaliMonth);
        }

        $base = $this->baseQuery($accommodationIds, $range);
        $provinces = $this->provinceStats($accommodationIds, $range);
        $cities = $this->cityStats($accommodationIds, $range);
        $confirmed = (clone $base)->where('bookings.status', 'confirmed');

        $kpiRow = (clone $base)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN bookings.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN bookings.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN bookings.status = 'confirmed' THEN bookings.nights ELSE 0 END) as nights,
                SUM(CASE WHEN bookings.status = 'confirmed' THEN bookings.guests ELSE 0 END) as guests,
                SUM(CASE WHEN bookings.status = 'confirmed' THEN bookings.discount_amount ELSE 0 END) as discount
            ")
            ->first();

        $confirmedCount = (int) ($kpiRow->confirmed ?? 0);
        $nights = (int) ($kpiRow->nights ?? 0);
        $discount = (int) ($kpiRow->discount ?? 0);

        $kpis = [
            'total'          => (int) ($kpiRow->total ?? 0),
            'confirmed'      => $confirmedCount,
            'pending'        => (int) ($kpiRow->pending ?? 0),
            'cancelled'      => (int) ($kpiRow->cancelled ?? 0),
            'nights'         => $nights,
            'guests'         => (int) ($kpiRow->guests ?? 0),
            'discount'       => $discount,
            'avg_nights'     => $confirmedCount > 0 ? round($nights / $confirmedCount, 1) : 0,
            'avg_discount'   => $confirmedCount > 0 ? (int) round($discount / $confirmedCount) : 0,
            'provinces'      => $provinces->count(),
            'cities'         => $cities->count(),
            'accommodations' => (int) (clone $confirmed)->distinct()->count('bookings.accommodation_id'),
            'programs'       => $this->supportiveProgramCount($accommodationIds, $range),
            'shared_groups'  => $this->sharedGroupStats($accommodationIds, $range),
        ];

        return [
            'kpis'           => $kpis,
            'range'          => $range,
            'period_label'   => $this->periodLabel($period, $jalaliYear, $jalaliMonth),
            'provinces'      => $provinces,
            'cities'         => $cities,
            'employers'      => $this->groupStats($confirmed, 'employer'),
            'accommodations' => $this->groupStats($confirmed, 'accommodation'),
        ];
    }

    /**
     * @param  array<int>  $accommodationIds
     * @param  array{from: string, to: string}|null  $range
     */
    public function cityBookings(array $accommodationIds, ?array $range, int $cityId): Collection
    {
        if ($accommodationIds === [] || $cityId < 1) {
            return collect();
        }

        return $this->baseQuery($accommodationIds, $range)
            ->with(['accommodation', 'employer', 'program.employer', 'user'])
            ->join('accommodations as acc_city', 'bookings.accommodation_id', '=', 'acc_city.id')
            ->where('acc_city.city_id', $cityId)
            ->select('bookings.*')
            ->orderByDesc('bookings.check_in')
            ->orderByDesc('bookings.id')
            ->limit(80)
            ->get();
    }

    /**
     * @param  array<int>  $accommodationIds
     * @param  array{from: string, to: string}|null  $range
     */
    public function provinceBookings(array $accommodationIds, ?array $range, int $provinceId): Collection
    {
        if ($accommodationIds === [] || $provinceId < 1) {
            return collect();
        }

        return $this->baseQuery($accommodationIds, $range)
            ->with(['accommodation.city', 'employer', 'program.employer', 'user'])
            ->join('accommodations as acc_pv', 'bookings.accommodation_id', '=', 'acc_pv.id')
            ->join('cities as city_pv', 'acc_pv.city_id', '=', 'city_pv.id')
            ->where('city_pv.province_id', $provinceId)
            ->select('bookings.*')
            ->orderByDesc('bookings.check_in')
            ->orderByDesc('bookings.id')
            ->limit(80)
            ->get();
    }

    public function reportGroupLabel(Booking $booking): string
    {
        if ($this->isSupportiveProgramBooking($booking)) {
            return SupportiveServicesReportGroups::labelForKey(
                SupportiveServicesReportGroups::KEY_SUPPORTIVE_PROGRAM
            );
        }

        $keys = $this->veteranGroupKeysForBooking($booking);

        if ($keys === []) {
            return '—';
        }

        return collect($keys)
            ->map(fn (string $key) => SupportiveServicesReportGroups::labelForKey($key))
            ->join(' + ');
    }

    /**
     * @param  array<int>  $accommodationIds
     * @param  array{from: string, to: string}|null  $range
     */
    private function baseQuery(array $accommodationIds, ?array $range): Builder
    {
        $veteranKeys = SupportiveServicesReportGroups::veteranGroupKeys();

        $query = Booking::query()
            ->whereIn('bookings.accommodation_id', $accommodationIds)
            ->where(function ($outer) use ($veteranKeys) {
                $outer->whereHas('program', function ($program) {
                    $program->where('payment_type', Program::PAYMENT_SUPPORTIVE)
                        ->where('status', '!=', Program::STATUS_CANCELLED);
                })
                    ->orWhere(function ($inner) use ($veteranKeys) {
                        $inner->where(function ($medical) {
                            $medical->where('is_medical_accommodation', false)
                                ->where(function ($payment) {
                                    $payment->whereNull('payment_method')
                                        ->orWhere('payment_method', '!=', Booking::PAYMENT_MEDICAL_ACCOMMODATION);
                                });
                        });

                        $this->applyBaseVeteranGroupFilter($inner, $veteranKeys);
                    });
            });

        if ($range) {
            $query->where('bookings.check_in', '<', $range['to'])
                ->where('bookings.check_out', '>', $range['from']);
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $veteranKeys
     */
    private function applyBaseVeteranGroupFilter(Builder $query, array $veteranKeys): void
    {
        $query->where(function ($veteran) use ($veteranKeys) {
            $veteran->whereIn('veteran_type_applied', $veteranKeys)
                ->orWhereIn('secondary_veteran_type_applied', $veteranKeys);

            $driver = DB::getDriverName();
            foreach ($veteranKeys as $key) {
                if ($driver === 'sqlite') {
                    $veteran->orWhereRaw(
                        "json_extract(veteran_accommodation_group_usage, '$." . $key . "') IS NOT NULL"
                    );
                } else {
                    $veteran->orWhereRaw(
                        "JSON_EXTRACT(veteran_accommodation_group_usage, '$." . $key . "') IS NOT NULL"
                    );
                }
            }
        });
    }

    /**
     * @param  array<int>  $accommodationIds
     * @param  array{from: string, to: string}|null  $range
     */
    private function provinceStats(array $accommodationIds, ?array $range): Collection
    {
        $confirmed = "bookings.status = 'confirmed'";

        return $this->baseQuery($accommodationIds, $range)
            ->join('accommodations', 'bookings.accommodation_id', '=', 'accommodations.id')
            ->join('cities', 'accommodations.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id')
            ->selectRaw("
                provinces.id as province_id,
                provinces.name as province,
                SUM(CASE WHEN {$confirmed} THEN 1 ELSE 0 END) as bookings,
                SUM(CASE WHEN bookings.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN bookings.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN {$confirmed} THEN bookings.nights ELSE 0 END) as nights,
                SUM(CASE WHEN {$confirmed} THEN bookings.guests ELSE 0 END) as guests,
                SUM(CASE WHEN {$confirmed} THEN bookings.discount_amount ELSE 0 END) as discount,
                COUNT(DISTINCT CASE WHEN {$confirmed} THEN accommodations.city_id END) as cities,
                COUNT(DISTINCT CASE WHEN {$confirmed} THEN bookings.accommodation_id END) as accommodations
            ")
            ->groupBy('provinces.id', 'provinces.name')
            ->orderByRaw("SUM(CASE WHEN {$confirmed} THEN 1 ELSE 0 END) DESC")
            ->get();
    }

    /**
     * @param  array<int>  $accommodationIds
     * @param  array{from: string, to: string}|null  $range
     */
    private function cityStats(array $accommodationIds, ?array $range): Collection
    {
        return $this->baseQuery($accommodationIds, $range)
            ->where('bookings.status', 'confirmed')
            ->join('accommodations', 'bookings.accommodation_id', '=', 'accommodations.id')
            ->join('cities', 'accommodations.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id')
            ->selectRaw("
                cities.id as city_id,
                cities.name as city,
                provinces.id as province_id,
                provinces.name as province,
                COUNT(*) as bookings,
                SUM(bookings.nights) as nights,
                SUM(bookings.discount_amount) as discount,
                AVG(accommodations.lat) as lat,
                AVG(accommodations.lng) as lng
            ")
            ->groupBy('cities.id', 'cities.name', 'provinces.id', 'provinces.name')
            ->orderByRaw('COUNT(*) DESC')
            ->get();
    }

    /**
     * @param  Builder<Booking>  $confirmed
     */
    private function groupStats(Builder $confirmed, string $kind): Collection
    {
        $aggregates = "
            COUNT(*) as bookings,
            SUM(bookings.nights) as nights,
            SUM(bookings.discount_amount) as discount
        ";

        if ($kind === 'employer') {
            return (clone $confirmed)
                ->leftJoin('programs as p', 'p.booking_id', '=', 'bookings.id')
                ->leftJoin('program_employers as be', 'bookings.program_employer_id', '=', 'be.id')
                ->leftJoin('program_employers as pe', 'p.program_employer_id', '=', 'pe.id')
                ->selectRaw("COALESCE(MAX(pe.name), MAX(be.name), 'بدون کارفرما') as label, {$aggregates}")
                ->groupByRaw('COALESCE(p.program_employer_id, bookings.program_employer_id, 0)')
                ->orderByRaw('COUNT(*) DESC')
                ->limit(12)
                ->get();
        }

        return (clone $confirmed)
            ->join('accommodations as a', 'bookings.accommodation_id', '=', 'a.id')
            ->selectRaw("MAX(a.name) as label, a.id as accommodation_id, {$aggregates}")
            ->groupBy('a.id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(12)
            ->get();
    }

    /**
     * @param  array<int>  $accommodationIds
     * @param  array{from: string, to: string}|null  $range
     * @return list<array{key:string,label:string,bookings:int,nights:int,guests:int,discount:int,avg_discount:int}>
     */
    private function sharedGroupStats(array $accommodationIds, ?array $range): array
    {
        $groups = SupportiveServicesReportGroups::sharedReportGroups();
        $stats = [];

        foreach ($groups as $group) {
            $stats[$group['key']] = [
                'key'          => $group['key'],
                'label'        => $group['label'],
                'bookings'     => 0,
                'nights'       => 0,
                'guests'       => 0,
                'discount'     => 0,
                'avg_discount' => 0,
            ];
        }

        $supportiveKey = SupportiveServicesReportGroups::KEY_SUPPORTIVE_PROGRAM;
        $supportiveRow = $this->baseQuery($accommodationIds, $range)
            ->where('bookings.status', 'confirmed')
            ->join('programs', 'programs.booking_id', '=', 'bookings.id')
            ->where('programs.payment_type', Program::PAYMENT_SUPPORTIVE)
            ->where('programs.status', '!=', Program::STATUS_CANCELLED)
            ->selectRaw('
                COUNT(*) as bookings,
                SUM(bookings.nights) as nights,
                SUM(bookings.guests) as guests,
                SUM(COALESCE(programs.discount_amount, bookings.discount_amount)) as discount
            ')
            ->first();

        $supportiveBookings = (int) ($supportiveRow->bookings ?? 0);
        $supportiveDiscount = (int) ($supportiveRow->discount ?? 0);
        $stats[$supportiveKey]['bookings'] = $supportiveBookings;
        $stats[$supportiveKey]['nights'] = (int) ($supportiveRow->nights ?? 0);
        $stats[$supportiveKey]['guests'] = (int) ($supportiveRow->guests ?? 0);
        $stats[$supportiveKey]['discount'] = $supportiveDiscount;
        $stats[$supportiveKey]['avg_discount'] = $supportiveBookings > 0
            ? (int) round($supportiveDiscount / $supportiveBookings)
            : 0;

        $veteranKeys = SupportiveServicesReportGroups::veteranGroupKeys();
        $bookingIdsPerGroup = array_fill_keys($veteranKeys, []);

        $veteranQuery = $this->baseQuery($accommodationIds, $range)
            ->where('bookings.status', 'confirmed')
            ->whereDoesntHave('program', function ($program) {
                $program->where('payment_type', Program::PAYMENT_SUPPORTIVE)
                    ->where('status', '!=', Program::STATUS_CANCELLED);
            });

        $this->applyBaseVeteranGroupFilter($veteranQuery, $veteranKeys);

        $veteranQuery
            ->select('bookings.id')
            ->orderBy('bookings.id')
            ->chunkById(40, function ($chunk) use (&$stats, &$bookingIdsPerGroup, $veteranKeys) {
                $bookings = Booking::query()
                    ->whereIn('id', $chunk->pluck('id'))
                    ->with(['services', 'guestDetails', 'accommodation', 'bookingRooms.roomType', 'bookingRooms.roomRate', 'user'])
                    ->get();

                foreach ($bookings as $booking) {
                    $pricing = $this->breakdown->forBooking($booking);
                    $groupsInBooking = [];

                    foreach ($pricing['accommodation_discount_breakdown'] ?? [] as $item) {
                        $key = (string) ($item['veteran_group_key'] ?? '');
                        if ($key === '' || !isset($stats[$key])) {
                            continue;
                        }

                        $stats[$key]['nights'] += (int) ($item['units'] ?? 0);
                        $stats[$key]['discount'] += (int) ($item['discount_amount'] ?? 0);
                        $groupsInBooking[$key] = true;
                    }

                    if ($groupsInBooking === []) {
                        foreach ($pricing['veteran_accommodation_group_usage'] ?? [] as $key => $nights) {
                            if (!isset($stats[$key]) || (int) $nights <= 0) {
                                continue;
                            }

                            $stats[$key]['nights'] += (int) $nights;
                            $groupsInBooking[$key] = true;
                        }
                    }

                    foreach (array_keys($groupsInBooking) as $key) {
                        $bookingIdsPerGroup[$key][$booking->id] = true;
                    }
                }
            }, column: 'id');

        foreach ($bookingIdsPerGroup as $key => $bookingIds) {
            $count = count($bookingIds);
            $stats[$key]['bookings'] = $count;
            $stats[$key]['avg_discount'] = $count > 0
                ? (int) round($stats[$key]['discount'] / $count)
                : 0;
        }

        return array_values($stats);
    }

    /**
     * @param  array<int>  $accommodationIds
     * @param  array{from: string, to: string}|null  $range
     */
    private function supportiveProgramCount(array $accommodationIds, ?array $range): int
    {
        $query = Program::query()
            ->whereIn('accommodation_id', $accommodationIds)
            ->where('payment_type', Program::PAYMENT_SUPPORTIVE)
            ->where('status', '!=', Program::STATUS_CANCELLED);

        if ($range) {
            $query->whereHas('booking', function ($booking) use ($range) {
                $booking->where('check_in', '<', $range['to'])
                    ->where('check_out', '>', $range['from']);
            });
        }

        return (int) $query->count();
    }

    private function isSupportiveProgramBooking(Booking $booking): bool
    {
        $program = $booking->relationLoaded('program') ? $booking->program : $booking->program()->first();

        return $program
            && $program->payment_type === Program::PAYMENT_SUPPORTIVE
            && $program->status !== Program::STATUS_CANCELLED;
    }

    /**
     * @return list<string>
     */
    private function veteranGroupKeysForBooking(Booking $booking): array
    {
        $allowed = SupportiveServicesReportGroups::veteranGroupKeys();
        $keys = [];

        foreach ([$booking->veteran_type_applied, $booking->secondary_veteran_type_applied] as $type) {
            if ($type && in_array($type, $allowed, true)) {
                $keys[] = $type;
            }
        }

        foreach (array_keys($booking->veteran_accommodation_group_usage ?? []) as $key) {
            if (in_array($key, $allowed, true)) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<array{key:string,label:string,bookings:int,nights:int,guests:int,discount:int,avg_discount:int}>
     */
    private function emptySharedGroups(): array
    {
        return array_map(fn (array $group) => [
            'key'          => $group['key'],
            'label'        => $group['label'],
            'bookings'     => 0,
            'nights'       => 0,
            'guests'       => 0,
            'discount'     => 0,
            'avg_discount' => 0,
        ], SupportiveServicesReportGroups::sharedReportGroups());
    }

    /**
     * @param  array{from: string, to: string}|null  $range
     * @return array<string, mixed>
     */
    private function emptyReport(?array $range, string $period, int $jalaliYear, int $jalaliMonth): array
    {
        return [
            'kpis' => [
                'total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0,
                'nights' => 0, 'guests' => 0, 'discount' => 0,
                'avg_nights' => 0, 'avg_discount' => 0,
                'provinces' => 0, 'cities' => 0, 'accommodations' => 0, 'programs' => 0,
                'shared_groups' => $this->emptySharedGroups(),
            ],
            'range'          => $range,
            'period_label'   => $this->periodLabel($period, $jalaliYear, $jalaliMonth),
            'provinces'      => collect(),
            'cities'         => collect(),
            'employers'      => collect(),
            'accommodations' => collect(),
        ];
    }
}
