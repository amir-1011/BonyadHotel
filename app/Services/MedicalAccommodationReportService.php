<?php

namespace App\Services;

use App\Models\Booking;
use App\Support\MedicalAccommodationTariffs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class MedicalAccommodationReportService
{
    /**
     * @return array{from: string, to: string}|null  Inclusive-start, exclusive-end (Y-m-d)
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
        $empty = $accommodationIds === [];

        if ($empty) {
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
                SUM(CASE WHEN bookings.status = 'confirmed' THEN bookings.medical_companion_count ELSE 0 END) as companions,
                SUM(CASE WHEN bookings.status = 'confirmed' THEN COALESCE(NULLIF(bookings.employer_debt_amount, 0), bookings.total_price) ELSE 0 END) as debt
            ")
            ->first();

        $confirmedCount = (int) ($kpiRow->confirmed ?? 0);
        $nights = (int) ($kpiRow->nights ?? 0);
        $debt = (int) ($kpiRow->debt ?? 0);

        $kpis = [
            'total'            => (int) ($kpiRow->total ?? 0),
            'confirmed'        => $confirmedCount,
            'pending'          => (int) ($kpiRow->pending ?? 0),
            'cancelled'        => (int) ($kpiRow->cancelled ?? 0),
            'nights'           => $nights,
            'guests'           => (int) ($kpiRow->guests ?? 0),
            'companions'       => (int) ($kpiRow->companions ?? 0),
            'debt'             => $debt,
            'avg_nights'       => $confirmedCount > 0 ? round($nights / $confirmedCount, 1) : 0,
            'avg_debt'         => $confirmedCount > 0 ? (int) round($debt / $confirmedCount) : 0,
            'provinces'        => $provinces->count(),
            'cities'           => $cities->count(),
            'accommodations'   => (int) (clone $confirmed)->distinct()->count('bookings.accommodation_id'),
            'contracts'        => (int) (clone $confirmed)->whereNotNull('bookings.medical_contract_id')->distinct()->count('bookings.medical_contract_id'),
            'shared_groups'    => $this->sharedGroupStats($confirmed),
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
            ->with(['accommodation', 'employer', 'medicalTariff', 'medicalContract', 'user'])
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
            ->with(['accommodation.city', 'employer', 'medicalTariff', 'medicalContract', 'user'])
            ->join('accommodations as acc_pv', 'bookings.accommodation_id', '=', 'acc_pv.id')
            ->join('cities as city_pv', 'acc_pv.city_id', '=', 'city_pv.id')
            ->where('city_pv.province_id', $provinceId)
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
    private function baseQuery(array $accommodationIds, ?array $range): Builder
    {
        $query = Booking::query()
            ->medicalAccommodation()
            ->whereIn('bookings.accommodation_id', $accommodationIds);

        if ($range) {
            $query->where('bookings.check_in', '<', $range['to'])
                ->where('bookings.check_out', '>', $range['from']);
        }

        return $query;
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
                SUM(CASE WHEN {$confirmed} THEN bookings.medical_companion_count ELSE 0 END) as companions,
                SUM(CASE WHEN {$confirmed} THEN COALESCE(NULLIF(bookings.employer_debt_amount, 0), bookings.total_price) ELSE 0 END) as debt,
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
                SUM(COALESCE(NULLIF(bookings.employer_debt_amount, 0), bookings.total_price)) as debt,
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
            SUM(COALESCE(NULLIF(bookings.employer_debt_amount, 0), bookings.total_price)) as debt
        ";

        if ($kind === 'employer') {
            return (clone $confirmed)
                ->leftJoin('program_employers as e', 'bookings.program_employer_id', '=', 'e.id')
                ->selectRaw("COALESCE(MAX(e.name), 'بدون کارفرما') as label, {$aggregates}")
                ->groupBy('bookings.program_employer_id')
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
     * @param  Builder<Booking>  $confirmed
     * @return list<array{key:string,label:string,bookings:int,nights:int,guests:int,companions:int,debt:int,avg_debt:int}>
     */
    private function sharedGroupStats(Builder $confirmed): array
    {
        $groups = MedicalAccommodationTariffs::sharedReportGroups();
        $driver = DB::getDriverName();
        $jsonKey = $driver === 'sqlite'
            ? "json_extract(bookings.medical_tariff_snapshot, '$.key')"
            : "JSON_UNQUOTE(JSON_EXTRACT(bookings.medical_tariff_snapshot, '$.key'))";
        $resolvedKey = "COALESCE(t.key, {$jsonKey})";
        $debtExpr = 'COALESCE(NULLIF(bookings.employer_debt_amount, 0), bookings.total_price)';

        $selects = [];
        foreach ($groups as $group) {
            $key = $group['key'];
            $selects[] = "SUM(CASE WHEN {$resolvedKey} = '{$key}' THEN 1 ELSE 0 END) as {$key}_bookings";
            $selects[] = "SUM(CASE WHEN {$resolvedKey} = '{$key}' THEN bookings.nights ELSE 0 END) as {$key}_nights";
            $selects[] = "SUM(CASE WHEN {$resolvedKey} = '{$key}' THEN bookings.guests ELSE 0 END) as {$key}_guests";
            $selects[] = "SUM(CASE WHEN {$resolvedKey} = '{$key}' THEN bookings.medical_companion_count ELSE 0 END) as {$key}_companions";
            $selects[] = "SUM(CASE WHEN {$resolvedKey} = '{$key}' THEN {$debtExpr} ELSE 0 END) as {$key}_debt";
        }

        $row = (clone $confirmed)
            ->leftJoin('medical_accommodation_tariffs as t', 'bookings.medical_tariff_id', '=', 't.id')
            ->selectRaw(implode(', ', $selects))
            ->first();

        $out = [];
        foreach ($groups as $group) {
            $key = $group['key'];
            $bookings = (int) ($row->{$key.'_bookings'} ?? 0);
            $debt = (int) ($row->{$key.'_debt'} ?? 0);
            $out[] = [
                'key'        => $key,
                'label'      => $group['label'],
                'bookings'   => $bookings,
                'nights'     => (int) ($row->{$key.'_nights'} ?? 0),
                'guests'     => (int) ($row->{$key.'_guests'} ?? 0),
                'companions' => (int) ($row->{$key.'_companions'} ?? 0),
                'debt'       => $debt,
                'avg_debt'   => $bookings > 0 ? (int) round($debt / $bookings) : 0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key:string,label:string,bookings:int,nights:int,guests:int,companions:int,debt:int,avg_debt:int}>
     */
    private function emptySharedGroups(): array
    {
        return array_map(fn (array $group) => [
            'key'        => $group['key'],
            'label'      => $group['label'],
            'bookings'   => 0,
            'nights'     => 0,
            'guests'     => 0,
            'companions' => 0,
            'debt'       => 0,
            'avg_debt'   => 0,
        ], MedicalAccommodationTariffs::sharedReportGroups());
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
                'nights' => 0, 'guests' => 0, 'companions' => 0, 'debt' => 0,
                'avg_nights' => 0, 'avg_debt' => 0,
                'provinces' => 0, 'cities' => 0, 'accommodations' => 0, 'contracts' => 0,
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
