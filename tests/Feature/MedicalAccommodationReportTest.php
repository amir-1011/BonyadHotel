<?php

namespace Tests\Feature;

use App\Livewire\Admin\MedicalAccommodationReport;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\User;
use App\Services\MedicalAccommodationReportService;
use App\Support\MedicalAccommodationTariffs;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MedicalAccommodationReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $host;

    private Accommodation $shirazHotel;

    private Accommodation $sariHotel;

    private int $farsId;

    private int $mazandaranId;

    private int $shirazId;

    private int $sariId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->admin = User::create(['name' => 'ادمین گزارش', 'mobile' => '09123000901']);
        $this->admin->assignRole('super_admin');

        $this->host = User::create(['name' => 'میزبان گزارش', 'mobile' => '09123000902']);
        $this->host->assignRole('host');

        $this->farsId = $this->ensureTestProvinceId('فارس', '507');
        $this->mazandaranId = $this->ensureTestProvinceId('مازندران', '521');
        $this->shirazId = $this->ensureTestCityId($this->farsId, 'شیراز');
        $this->sariId = $this->ensureTestCityId($this->mazandaranId, 'ساری');

        $this->shirazHotel = $this->createTestAccommodation([
            'name'    => 'هتل شیراز درمانی',
            'city_id' => $this->shirazId,
            'lat'     => 29.5918,
            'lng'     => 52.5836,
        ]);
        $this->sariHotel = $this->createTestAccommodation([
            'name'    => 'هتل ساری درمانی',
            'city_id' => $this->sariId,
            'lat'     => 36.5633,
            'lng'     => 53.0601,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guests_are_redirected_and_hosts_are_forbidden(): void
    {
        $this->get(route('admin.medical-accommodation-report'))
            ->assertRedirect();

        $this->actingAs($this->host)
            ->get(route('admin.medical-accommodation-report'))
            ->assertForbidden();
    }

    public function test_host_report_is_scoped_to_managed_accommodations(): void
    {
        $this->host->accommodations()->attach($this->shirazHotel->id);
        $this->host->update([
            'host_panel_permissions' => [
                'bookings.list' => ['read'],
                'bookings.medical-accommodation-report' => ['read'],
                'bookings.show' => ['read'],
            ],
        ]);

        $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'   => 'مهمان شیراز میزبان',
            'employer_debt_amount' => 6_000_000,
        ]);
        $this->createMedicalBooking($this->sariHotel, [
            'guest_contact_name'   => 'مهمان ساری خارج از دسترسی',
            'employer_debt_amount' => 9_000_000,
        ]);

        $this->actingAs($this->host)
            ->get(route('host.medical-accommodation-report'))
            ->assertOk()
            ->assertSee('اسکان درمانی')
            ->assertSee('هتل شیراز درمانی')
            ->assertSee('فارس')
            ->assertDontSee('هتل ساری درمانی')
            ->assertDontSee('مازندران');

        Livewire::actingAs($this->host)
            ->test(\App\Livewire\Host\MedicalAccommodationReport::class)
            ->assertSee('همه اقامتگاه‌ها (1)')
            ->call('openCity', $this->shirazId)
            ->assertSee('مهمان شیراز میزبان')
            ->assertDontSee('مهمان ساری خارج از دسترسی');
    }

    public function test_host_without_report_permission_is_forbidden(): void
    {
        $this->host->accommodations()->attach($this->shirazHotel->id);
        $this->host->update([
            'host_panel_permissions' => [
                'bookings.list' => ['read'],
            ],
        ]);

        $this->actingAs($this->host)
            ->get(route('host.medical-accommodation-report'))
            ->assertRedirect(route('host.bookings.index'));
    }

    public function test_admin_page_renders_filters_map_and_province_report(): void
    {
        $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'  => 'مهمان شیراز',
            'employer_debt_amount' => 8_000_000,
            'nights'              => 3,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.medical-accommodation-report'))
            ->assertOk()
            ->assertSee('اسکان درمانی')
            ->assertSee('پراکندگی رزروهای اسکان درمانی در ایران')
            ->assertSee('شهرهای پرتقاضا')
            ->assertSee('گزارش استان‌ها')
            ->assertSee('گزارش شهرها')
            ->assertSee('همه اقامتگاه‌ها')
            ->assertSee('فارس')
            ->assertSee('شیراز')
            ->assertSee('هتل شیراز درمانی')
            ->assertSee('medicalIranMap')
            ->assertSee('dragging: false')
            ->assertSee('scrollWheelZoom: false')
            ->assertSee('doubleClickZoom: false')
            ->assertSee('zoomControl: false')
            ->assertDontSee('روی استان کلیک کنید تا بزرگ شود')
            ->assertSee('جانبازان معزز گردنی')
            ->assertSee('جانبازان معزز نخاعی و قطع دو عضو بالای زانو')
            ->assertSee('سایر ایثارگران')
            ->assertSee('کارفرما / بیمه دی')
            ->assertDontSee('شماره قرارداد');
    }

    public function test_cash_bookings_are_excluded_from_medical_report(): void
    {
        $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'   => 'مهمان درمانی نمایان',
            'employer_debt_amount' => 4_000_000,
        ]);
        $this->createCashBooking($this->shirazHotel, [
            'guest_contact_name' => 'مهمان نقدی پنهان',
            'total_price'        => 9_999_999,
        ]);

        $report = app(MedicalAccommodationReportService::class)->build(
            [$this->shirazHotel->id, $this->sariHotel->id],
            'all',
            1405,
            6,
        );

        $this->assertSame(1, $report['kpis']['confirmed']);
        $this->assertSame(4_000_000, $report['kpis']['debt']);
        $this->assertSame('فارس', $report['provinces']->first()->province);

        Livewire::actingAs($this->admin)
            ->test(MedicalAccommodationReport::class)
            ->call('openCity', $this->shirazId)
            ->assertSee('مهمان درمانی نمایان')
            ->assertDontSee('مهمان نقدی پنهان');
    }

    public function test_cancelled_bookings_are_counted_separately_and_appear_in_city_modal(): void
    {
        $confirmed = $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'   => 'تأیید شیراز',
            'tracking_code'        => 'MEDCONF1',
            'employer_debt_amount' => 3_000_000,
            'nights'               => 2,
        ]);
        $cancelled = $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'   => 'کنسل شیراز',
            'tracking_code'        => 'MEDCANC1',
            'status'               => 'cancelled',
            'employer_debt_amount' => 7_000_000,
            'nights'               => 4,
        ]);

        $report = app(MedicalAccommodationReportService::class)->build(
            [$this->shirazHotel->id],
            'all',
            1405,
            6,
        );

        $this->assertSame(2, $report['kpis']['total']);
        $this->assertSame(1, $report['kpis']['confirmed']);
        $this->assertSame(1, $report['kpis']['cancelled']);
        $this->assertSame(2, $report['kpis']['nights']);
        $this->assertSame(3_000_000, $report['kpis']['debt']);
        $this->assertSame(1, (int) $report['provinces']->first()->cancelled);

        Livewire::actingAs($this->admin)
            ->test(MedicalAccommodationReport::class)
            ->call('openCity', $this->shirazId)
            ->assertSet('showCityModal', true)
            ->assertSee('MEDCONF1')
            ->assertSee('MEDCANC1')
            ->assertSee('تأیید شیراز')
            ->assertSee('کنسل شیراز');

        $this->assertTrue($confirmed->isMedicalAccommodation());
        $this->assertTrue($cancelled->isMedicalAccommodation());
    }

    public function test_accommodation_filter_scopes_province_and_city_stats(): void
    {
        $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'   => 'فقط شیراز',
            'employer_debt_amount' => 1_000_000,
        ]);
        $this->createMedicalBooking($this->sariHotel, [
            'guest_contact_name'   => 'فقط ساری',
            'employer_debt_amount' => 2_000_000,
        ]);

        $service = app(MedicalAccommodationReportService::class);
        $scoped = $service->build([$this->shirazHotel->id], 'all', 1405, 6);
        $this->assertSame(1, $scoped['kpis']['confirmed']);
        $this->assertSame(1_000_000, $scoped['kpis']['debt']);
        $this->assertSame(['فارس'], $scoped['provinces']->pluck('province')->all());
        $this->assertSame(['شیراز'], $scoped['cities']->pluck('city')->all());

        Livewire::actingAs($this->admin)
            ->test(MedicalAccommodationReport::class)
            ->assertSee('فارس')
            ->assertSee('مازندران')
            ->call('toggleDraftDashboardAccommodation', $this->sariHotel->id)
            ->call('applyDashboardAccommodationFilter')
            ->assertSet('dashboardAccommodationAllSelected', false)
            ->assertSee('فارس')
            ->assertDontSee('مازندران');
    }

    public function test_month_and_year_filters_use_stay_overlap(): void
    {
        $inMonth = Jalalian::fromFormat('Y/m/d', '1405/06/10')->toCarbon();
        $otherMonth = Jalalian::fromFormat('Y/m/d', '1405/05/10')->toCarbon();
        $otherYear = Jalalian::fromFormat('Y/m/d', '1404/06/10')->toCarbon();

        $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'   => 'شهریور ۱۴۰۵',
            'tracking_code'        => 'MEDM1405',
            'check_in'             => $inMonth->toDateString(),
            'check_out'            => $inMonth->copy()->addDays(2)->toDateString(),
            'nights'               => 2,
            'employer_debt_amount' => 1_000_000,
        ]);
        $this->createMedicalBooking($this->sariHotel, [
            'guest_contact_name'   => 'مرداد ۱۴۰۵',
            'tracking_code'        => 'MEDM1405B',
            'check_in'             => $otherMonth->toDateString(),
            'check_out'            => $otherMonth->copy()->addDays(2)->toDateString(),
            'nights'               => 2,
            'employer_debt_amount' => 2_000_000,
        ]);
        $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name'   => 'شهریور ۱۴۰۴',
            'tracking_code'        => 'MEDM1404',
            'check_in'             => $otherYear->toDateString(),
            'check_out'            => $otherYear->copy()->addDays(2)->toDateString(),
            'nights'               => 2,
            'employer_debt_amount' => 3_000_000,
        ]);

        $service = app(MedicalAccommodationReportService::class);
        $ids = [$this->shirazHotel->id, $this->sariHotel->id];

        $month = $service->build($ids, 'month', 1405, 6);
        $this->assertSame(1, $month['kpis']['confirmed']);
        $this->assertSame(1_000_000, $month['kpis']['debt']);
        $this->assertSame('فارس', $month['provinces']->first()->province);

        $year = $service->build($ids, 'year', 1405, 6);
        $this->assertSame(2, $year['kpis']['confirmed']);
        $this->assertSame(3_000_000, $year['kpis']['debt']);

        $all = $service->build($ids, 'all', 1405, 6);
        $this->assertSame(3, $all['kpis']['confirmed']);
        $this->assertSame(6_000_000, $all['kpis']['debt']);

        Livewire::actingAs($this->admin)
            ->test(MedicalAccommodationReport::class)
            ->call('setPeriod', 'month')
            ->assertSet('period', 'month')
            ->call('openCity', $this->shirazId)
            ->assertSee('MEDM1405')
            ->assertDontSee('MEDM1404')
            ->assertDontSee('MEDM1405B')
            ->call('closeModal')
            ->call('setPeriod', 'year')
            ->call('openCity', $this->shirazId)
            ->assertSee('MEDM1405')
            ->assertDontSee('MEDM1404')
            ->call('openCity', $this->sariId)
            ->assertSee('MEDM1405B')
            ->call('closeModal')
            ->call('setPeriod', 'all')
            ->call('openCity', $this->shirazId)
            ->assertSee('MEDM1404');
    }

    public function test_period_navigation_moves_month_and_year(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MedicalAccommodationReport::class)
            ->call('setPeriod', 'month')
            ->assertSet('jalaliYear', 1405)
            ->assertSet('jalaliMonth', 6)
            ->call('prevPeriod')
            ->assertSet('jalaliMonth', 5)
            ->call('nextPeriod')
            ->call('nextPeriod')
            ->assertSet('jalaliMonth', 7)
            ->call('setPeriod', 'year')
            ->call('prevPeriod')
            ->assertSet('jalaliYear', 1404)
            ->call('goToCurrentPeriod')
            ->assertSet('jalaliYear', 1405)
            ->assertSet('jalaliMonth', 6)
            ->assertDispatched('medical-report-map-refresh');
    }

    public function test_province_modal_lists_city_bookings(): void
    {
        $this->createMedicalBooking($this->shirazHotel, [
            'guest_contact_name' => 'مهمان استان فارس',
            'tracking_code'      => 'MEDFARS1',
        ]);
        $this->createMedicalBooking($this->sariHotel, [
            'guest_contact_name' => 'مهمان استان مازندران',
            'tracking_code'      => 'MEDMAZ1',
        ]);

        Livewire::actingAs($this->admin)
            ->test(MedicalAccommodationReport::class)
            ->call('openProvince', $this->farsId)
            ->assertSee('رزروهای اسکان درمانی')
            ->assertSee('استان فارس')
            ->assertSee('MEDFARS1')
            ->assertSee('مهمان استان فارس')
            ->assertDontSee('MEDMAZ1')
            ->call('closeModal')
            ->assertSet('showCityModal', false)
            ->call('openCity', $this->sariId)
            ->assertSee('MEDMAZ1')
            ->assertDontSee('MEDFARS1');
    }

    public function test_empty_accommodation_filter_returns_empty_kpis(): void
    {
        $this->createMedicalBooking($this->shirazHotel, [
            'employer_debt_amount' => 5_000_000,
        ]);

        $empty = app(MedicalAccommodationReportService::class)->build([], 'all', 1405, 6);
        $this->assertSame(0, $empty['kpis']['confirmed']);
        $this->assertSame(0, $empty['kpis']['debt']);
        $this->assertTrue($empty['provinces']->isEmpty());
        $this->assertCount(3, $empty['kpis']['shared_groups']);
        $this->assertSame(0, $empty['kpis']['shared_groups'][0]['bookings']);
        $this->assertSame(MedicalAccommodationTariffs::KEY_NECK_INJURY, $empty['kpis']['shared_groups'][0]['key']);
    }

    public function test_shared_tariff_groups_aggregate_confirmed_bookings_by_catalog_key(): void
    {
        $neckShiraz = $this->tariffFor($this->shirazHotel, MedicalAccommodationTariffs::KEY_NECK_INJURY);
        $neckSari = $this->tariffFor($this->sariHotel, MedicalAccommodationTariffs::KEY_NECK_INJURY);
        $spinal = $this->tariffFor($this->shirazHotel, MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE);
        $other = $this->tariffFor($this->sariHotel, MedicalAccommodationTariffs::KEY_OTHER_VETERAN);

        $this->createMedicalBooking($this->shirazHotel, [
            'tracking_code'            => 'GRPNECK1',
            'medical_tariff_id'        => $neckShiraz->id,
            'medical_tariff_snapshot'  => $neckShiraz->toQuoteSnapshot(),
            'nights'                   => 3,
            'guests'                   => 3,
            'medical_companion_count'  => 2,
            'employer_debt_amount'     => 10_000_000,
        ]);
        $this->createMedicalBooking($this->sariHotel, [
            'tracking_code'            => 'GRPNECK2',
            'medical_tariff_id'        => $neckSari->id,
            'medical_tariff_snapshot'  => $neckSari->toQuoteSnapshot(),
            'nights'                   => 2,
            'guests'                   => 2,
            'medical_companion_count'  => 1,
            'employer_debt_amount'     => 8_000_000,
        ]);
        $this->createMedicalBooking($this->shirazHotel, [
            'tracking_code'            => 'GRPSPIN1',
            'medical_tariff_id'        => $spinal->id,
            'medical_tariff_snapshot'  => $spinal->toQuoteSnapshot(),
            'nights'                   => 4,
            'guests'                   => 2,
            'medical_companion_count'  => 1,
            'employer_debt_amount'     => 7_200_000,
        ]);
        $this->createMedicalBooking($this->sariHotel, [
            'tracking_code'            => 'GRPOTH1',
            'medical_tariff_id'        => $other->id,
            'medical_tariff_snapshot'  => $other->toQuoteSnapshot(),
            'nights'                   => 1,
            'guests'                   => 2,
            'medical_companion_count'  => 1,
            'employer_debt_amount'     => 3_500_000,
        ]);
        $this->createMedicalBooking($this->shirazHotel, [
            'tracking_code'            => 'GRPSPINX',
            'status'                   => 'cancelled',
            'medical_tariff_id'        => $spinal->id,
            'medical_tariff_snapshot'  => $spinal->toQuoteSnapshot(),
            'nights'                   => 9,
            'employer_debt_amount'     => 99_000_000,
        ]);
        $this->createMedicalBooking($this->shirazHotel, [
            'tracking_code'            => 'GRPSNAP1',
            'medical_tariff_id'        => null,
            'medical_tariff_snapshot'  => [
                'key'   => MedicalAccommodationTariffs::KEY_OTHER_VETERAN,
                'label' => 'سایر ایثارگران',
            ],
            'nights'                   => 2,
            'guests'                   => 1,
            'medical_companion_count'  => 0,
            'employer_debt_amount'     => 4_000_000,
        ]);

        $report = app(MedicalAccommodationReportService::class)->build(
            [$this->shirazHotel->id, $this->sariHotel->id],
            'all',
            1405,
            6,
        );
        $groups = collect($report['kpis']['shared_groups'])->keyBy('key');

        $this->assertCount(3, $report['kpis']['shared_groups']);
        $this->assertSame(2, $groups[MedicalAccommodationTariffs::KEY_NECK_INJURY]['bookings']);
        $this->assertSame(5, $groups[MedicalAccommodationTariffs::KEY_NECK_INJURY]['nights']);
        $this->assertSame(5, $groups[MedicalAccommodationTariffs::KEY_NECK_INJURY]['guests']);
        $this->assertSame(3, $groups[MedicalAccommodationTariffs::KEY_NECK_INJURY]['companions']);
        $this->assertSame(18_000_000, $groups[MedicalAccommodationTariffs::KEY_NECK_INJURY]['debt']);
        $this->assertSame(1, $groups[MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE]['bookings']);
        $this->assertSame(4, $groups[MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE]['nights']);
        $this->assertSame(7_200_000, $groups[MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE]['debt']);
        $this->assertSame(2, $groups[MedicalAccommodationTariffs::KEY_OTHER_VETERAN]['bookings']);
        $this->assertSame(3, $groups[MedicalAccommodationTariffs::KEY_OTHER_VETERAN]['nights']);
        $this->assertSame(7_500_000, $groups[MedicalAccommodationTariffs::KEY_OTHER_VETERAN]['debt']);

        Livewire::actingAs($this->admin)
            ->test(MedicalAccommodationReport::class)
            ->assertSee('جانبازان معزز گردنی')
            ->assertSee('جانبازان معزز نخاعی و قطع دو عضو بالای زانو')
            ->assertSee('سایر ایثارگران');
    }

    public function test_range_for_all_month_and_year(): void
    {
        $service = app(MedicalAccommodationReportService::class);

        $this->assertNull($service->rangeFor('all', 1405, 6));
        $this->assertSame('همه تاریخ‌ها', $service->periodLabel('all', 1405, 6));

        $month = $service->rangeFor('month', 1405, 6);
        $this->assertSame(
            Jalalian::fromFormat('Y/m/d', '1405/06/01')->toCarbon()->format('Y-m-d'),
            $month['from'],
        );
        $this->assertSame(
            Jalalian::fromFormat('Y/m/d', '1405/07/01')->toCarbon()->format('Y-m-d'),
            $month['to'],
        );

        $year = $service->rangeFor('year', 1405, 6);
        $this->assertSame(
            Jalalian::fromFormat('Y/m/d', '1405/01/01')->toCarbon()->format('Y-m-d'),
            $year['from'],
        );
        $this->assertSame(
            Jalalian::fromFormat('Y/m/d', '1406/01/01')->toCarbon()->format('Y-m-d'),
            $year['to'],
        );
    }

    private function tariffFor(Accommodation $accommodation, string $key)
    {
        return $accommodation->medicalAccommodationTariffs()->where('key', $key)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMedicalBooking(Accommodation $accommodation, array $overrides = []): Booking
    {
        $guest = User::create([
            'name'   => $overrides['guest_contact_name'] ?? 'مهمان درمانی',
            'mobile' => '0912'.random_int(1000000, 9999999),
        ]);
        $guest->assignRole('guest');

        $contract = $accommodation->medicalAccommodationContracts()
            ->with(['tariffs', 'employer'])
            ->orderBy('id')
            ->first();
        $tariff = $contract?->tariffs->first();

        $nights = (int) ($overrides['nights'] ?? 2);
        $checkIn = $overrides['check_in'] ?? now()->addDays(8)->toDateString();
        $checkOut = $overrides['check_out'] ?? Carbon::parse($checkIn)->addDays($nights)->toDateString();

        unset($overrides['check_in'], $overrides['check_out'], $overrides['nights']);

        return Booking::create(array_merge([
            'user_id'                  => $guest->id,
            'accommodation_id'         => $accommodation->id,
            'check_in'                 => $checkIn,
            'check_out'                => $checkOut,
            'guests'                   => 2,
            'nights'                   => $nights,
            'base_price'               => 0,
            'discount_percentage'      => 0,
            'discount_amount'          => 0,
            'total_price'              => 0,
            'status'                   => 'confirmed',
            'tracking_code'            => 'M'.random_int(100000, 999999),
            'payment_method'           => Booking::PAYMENT_MEDICAL_ACCOMMODATION,
            'is_medical_accommodation' => true,
            'medical_contract_id'      => $contract?->id,
            'medical_tariff_id'        => $tariff?->id,
            'medical_tariff_snapshot'  => $tariff?->toQuoteSnapshot(),
            'program_employer_id'      => $contract?->program_employer_id,
            'employer_debt_amount'     => 5_000_000,
            'medical_companion_count'  => 1,
            'guest_contact_name'       => $guest->name,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCashBooking(Accommodation $accommodation, array $overrides = []): Booking
    {
        $guest = User::create([
            'name'   => $overrides['guest_contact_name'] ?? 'مهمان نقدی',
            'mobile' => '0913'.random_int(1000000, 9999999),
        ]);
        $guest->assignRole('guest');

        $total = (int) ($overrides['total_price'] ?? 2_000_000);
        unset($overrides['total_price']);

        return Booking::create(array_merge([
            'user_id'                  => $guest->id,
            'accommodation_id'         => $accommodation->id,
            'check_in'                 => now()->addDays(8)->toDateString(),
            'check_out'                => now()->addDays(10)->toDateString(),
            'guests'                   => 2,
            'nights'                   => 2,
            'base_price'               => $total,
            'discount_percentage'      => 0,
            'discount_amount'          => 0,
            'total_price'              => $total,
            'status'                   => 'confirmed',
            'tracking_code'            => 'C'.random_int(100000, 999999),
            'payment_method'           => Booking::PAYMENT_CASH,
            'is_medical_accommodation' => false,
            'guest_contact_name'       => $guest->name,
        ], $overrides));
    }
}
