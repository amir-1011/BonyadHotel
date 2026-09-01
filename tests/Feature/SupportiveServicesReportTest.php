<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProgramSupportiveReport;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Program;
use App\Models\ProgramEmployer;
use App\Models\User;
use App\Services\SupportiveServicesReportService;
use App\Support\SupportiveServicesReportGroups;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportiveServicesReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $host;

    private Accommodation $shirazHotel;

    private Accommodation $sariHotel;

    private int $shirazId;

    private int $sariId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-25 12:00:00');

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->admin = User::create(['name' => 'ادمین حمایتی', 'mobile' => '09123001001']);
        $this->admin->assignRole('super_admin');

        $this->host = User::create(['name' => 'میزبان حمایتی', 'mobile' => '09123001002']);
        $this->host->assignRole('host');

        $farsId = $this->ensureTestProvinceId('فارس', '507');
        $mazandaranId = $this->ensureTestProvinceId('مازندران', '521');
        $this->shirazId = $this->ensureTestCityId($farsId, 'شیراز');
        $this->sariId = $this->ensureTestCityId($mazandaranId, 'ساری');

        $this->shirazHotel = $this->createTestAccommodation([
            'name'    => 'هتل شیراز حمایتی',
            'city_id' => $this->shirazId,
            'lat'     => 29.5918,
            'lng'     => 52.5836,
        ]);
        $this->sariHotel = $this->createTestAccommodation([
            'name'    => 'هتل ساری حمایتی',
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

    public function test_admin_page_renders_supportive_report_dashboard(): void
    {
        $this->createSupportiveProgramBooking($this->shirazHotel, [
            'guest_contact_name' => 'مهمان حمایتی',
            'discount_amount'    => 2_000_000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.programs.supportive-report'))
            ->assertOk()
            ->assertSee('گزارش خدمات حمایتی')
            ->assertSee('پراکندگی رزروهای خدمات حمایتی در ایران')
            ->assertSee('برنامه / اردوی حمایتی')
            ->assertSee('جانبازان ۷۰ درصد و همسران')
            ->assertSee('همکاران و بازنشستگان بنیاد')
            ->assertSee('supportiveIranMap');
    }

    public function test_report_includes_supportive_programs_and_veteran_group_bookings(): void
    {
        $this->createSupportiveProgramBooking($this->shirazHotel, [
            'guest_contact_name' => 'اردوی حمایتی',
            'discount_amount'    => 3_000_000,
            'program_discount'   => 3_000_000,
        ]);
        $this->createVeteranGroupBooking($this->shirazHotel, [
            'guest_contact_name' => 'جانباز ۷۰',
            'veteran_type_applied' => 'veteran_70_spouses',
            'veteran_accommodation_group_usage' => ['veteran_70_spouses' => 2],
            'discount_amount' => 1_500_000,
        ]);

        $report = app(SupportiveServicesReportService::class)->build(
            [$this->shirazHotel->id],
            'all',
            1405,
            6,
        );

        $this->assertSame(2, $report['kpis']['confirmed']);
        $this->assertSame(4_500_000, $report['kpis']['discount']);
        $this->assertSame(1, $report['kpis']['programs']);

        $groups = collect($report['kpis']['shared_groups'])->keyBy('key');
        $this->assertSame(1, $groups[SupportiveServicesReportGroups::KEY_SUPPORTIVE_PROGRAM]['bookings']);
        $this->assertSame(3_000_000, $groups[SupportiveServicesReportGroups::KEY_SUPPORTIVE_PROGRAM]['discount']);
        $this->assertSame(1, $groups['veteran_70_spouses']['bookings']);
        $this->assertSame(2, $groups['veteran_70_spouses']['nights']);
    }

    public function test_medical_and_regular_cash_bookings_are_excluded(): void
    {
        $this->createSupportiveProgramBooking($this->shirazHotel, [
            'guest_contact_name' => 'فقط حمایتی',
            'discount_amount'    => 1_000_000,
        ]);
        $this->createMedicalLikeBooking($this->shirazHotel, [
            'guest_contact_name' => 'اسکان درمانی',
            'discount_amount'    => 9_000_000,
        ]);
        $this->createRegularCashBooking($this->shirazHotel, [
            'guest_contact_name' => 'نقدی عادی',
            'discount_amount'    => 500_000,
        ]);

        $report = app(SupportiveServicesReportService::class)->build(
            [$this->shirazHotel->id],
            'all',
            1405,
            6,
        );

        $this->assertSame(1, $report['kpis']['confirmed']);
        $this->assertSame(1_000_000, $report['kpis']['discount']);

        Livewire::actingAs($this->admin)
            ->test(ProgramSupportiveReport::class)
            ->call('openCity', $this->shirazId)
            ->assertSee('فقط حمایتی')
            ->assertDontSee('اسکان درمانی')
            ->assertDontSee('نقدی عادی');
    }

    public function test_host_report_is_scoped_to_managed_accommodations(): void
    {
        $this->host->accommodations()->attach($this->shirazHotel->id);
        $this->host->update([
            'host_panel_permissions' => [
                'programs.supportive-report' => ['read'],
                'programs.show' => ['read'],
            ],
        ]);

        $this->createSupportiveProgramBooking($this->shirazHotel, [
            'guest_contact_name' => 'شیراز میزبان',
            'discount_amount'    => 1_000_000,
        ]);
        $this->createSupportiveProgramBooking($this->sariHotel, [
            'guest_contact_name' => 'ساری خارج',
            'discount_amount'    => 2_000_000,
        ]);

        $this->actingAs($this->host)
            ->get(route('host.programs.supportive-report'))
            ->assertOk()
            ->assertSee('هتل شیراز حمایتی')
            ->assertSee('فارس')
            ->assertDontSee('هتل ساری حمایتی')
            ->assertDontSee('مازندران');
    }

    public function test_shared_report_groups_include_all_base_veteran_groups(): void
    {
        $groups = SupportiveServicesReportGroups::sharedReportGroups();

        $this->assertSame(9, count($groups));
        $this->assertSame(
            SupportiveServicesReportGroups::KEY_SUPPORTIVE_PROGRAM,
            $groups[0]['key'],
        );
        $this->assertSame('foundation_staff_retirees', $groups[8]['key']);
        $this->assertSame('همکاران و بازنشستگان بنیاد', $groups[8]['label']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSupportiveProgramBooking(Accommodation $accommodation, array $overrides = []): Booking
    {
        $guest = User::create([
            'name'   => $overrides['guest_contact_name'] ?? 'مهمان حمایتی',
            'mobile' => '0914'.random_int(1000000, 9999999),
        ]);
        $guest->assignRole('guest');

        $employer = ProgramEmployer::create([
            'name'                    => 'بنیاد شهید',
            'employer_code'           => 'EMP-SUP-'.random_int(100, 999),
            'national_or_economic_id' => '1122334455',
            'mobile'                  => '09121112233',
        ]);

        $nights = (int) ($overrides['nights'] ?? 2);
        $checkIn = $overrides['check_in'] ?? now()->addDays(8)->toDateString();
        $checkOut = $overrides['check_out'] ?? Carbon::parse($checkIn)->addDays($nights)->toDateString();
        $programDiscount = (int) ($overrides['program_discount'] ?? $overrides['discount_amount'] ?? 1_000_000);

        unset(
            $overrides['check_in'],
            $overrides['check_out'],
            $overrides['nights'],
            $overrides['program_discount'],
        );

        $booking = Booking::create(array_merge([
            'user_id'             => $guest->id,
            'accommodation_id'    => $accommodation->id,
            'check_in'            => $checkIn,
            'check_out'           => $checkOut,
            'guests'              => 2,
            'nights'              => $nights,
            'base_price'          => 4_000_000,
            'discount_amount'     => $programDiscount,
            'total_price'         => 4_000_000 - $programDiscount,
            'status'              => 'confirmed',
            'tracking_code'       => 'S'.random_int(100000, 999999),
            'payment_method'      => Booking::PAYMENT_CASH,
            'guest_contact_name'  => $guest->name,
        ], $overrides));

        Program::create([
            'booking_id'       => $booking->id,
            'accommodation_id' => $accommodation->id,
            'created_by'       => $this->admin->id,
            'title'            => 'اردوی حمایتی تست',
            'program_type'     => Program::TYPE_CAMP,
            'program_employer_id' => $employer->id,
            'guest_count'      => 2,
            'rooms_allocated'  => 1,
            'payment_type'     => Program::PAYMENT_SUPPORTIVE,
            'base_price'       => 4_000_000,
            'discount_amount'  => $programDiscount,
            'total_amount'     => 4_000_000 - $programDiscount,
            'status'           => Program::STATUS_ACTIVE,
        ]);

        return $booking->fresh(['program']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createVeteranGroupBooking(Accommodation $accommodation, array $overrides = []): Booking
    {
        $guest = User::create([
            'name'   => $overrides['guest_contact_name'] ?? 'مهمان ایثارگر',
            'mobile' => '0915'.random_int(1000000, 9999999),
        ]);
        $guest->assignRole('guest');

        return Booking::create(array_merge([
            'user_id'             => $guest->id,
            'accommodation_id'    => $accommodation->id,
            'check_in'            => now()->addDays(8)->toDateString(),
            'check_out'           => now()->addDays(10)->toDateString(),
            'guests'              => 2,
            'nights'              => 2,
            'base_price'          => 3_000_000,
            'discount_amount'     => 1_000_000,
            'total_price'         => 2_000_000,
            'status'              => 'confirmed',
            'tracking_code'       => 'V'.random_int(100000, 999999),
            'payment_method'      => Booking::PAYMENT_CASH,
            'guest_contact_name'  => $guest->name,
            'veteran_type_applied'=> 'veteran_70_spouses',
            'veteran_accommodation_group_usage' => ['veteran_70_spouses' => 2],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMedicalLikeBooking(Accommodation $accommodation, array $overrides = []): Booking
    {
        $guest = User::create([
            'name'   => $overrides['guest_contact_name'] ?? 'مهمان درمانی',
            'mobile' => '0916'.random_int(1000000, 9999999),
        ]);
        $guest->assignRole('guest');

        return Booking::create(array_merge([
            'user_id'                  => $guest->id,
            'accommodation_id'         => $accommodation->id,
            'check_in'                 => now()->addDays(8)->toDateString(),
            'check_out'                => now()->addDays(10)->toDateString(),
            'guests'                   => 2,
            'nights'                   => 2,
            'base_price'               => 0,
            'discount_amount'          => 0,
            'total_price'              => 5_000_000,
            'status'                   => 'confirmed',
            'tracking_code'            => 'M'.random_int(100000, 999999),
            'payment_method'           => Booking::PAYMENT_MEDICAL_ACCOMMODATION,
            'is_medical_accommodation' => true,
            'guest_contact_name'       => $guest->name,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRegularCashBooking(Accommodation $accommodation, array $overrides = []): Booking
    {
        $guest = User::create([
            'name'   => $overrides['guest_contact_name'] ?? 'مهمان نقدی',
            'mobile' => '0917'.random_int(1000000, 9999999),
        ]);
        $guest->assignRole('guest');

        return Booking::create(array_merge([
            'user_id'             => $guest->id,
            'accommodation_id'    => $accommodation->id,
            'check_in'            => now()->addDays(8)->toDateString(),
            'check_out'           => now()->addDays(10)->toDateString(),
            'guests'              => 2,
            'nights'              => 2,
            'base_price'          => 2_000_000,
            'discount_amount'     => 0,
            'total_price'         => 2_000_000,
            'status'              => 'confirmed',
            'tracking_code'       => 'C'.random_int(100000, 999999),
            'payment_method'      => Booking::PAYMENT_CASH,
            'guest_contact_name'  => $guest->name,
        ], $overrides));
    }
}
