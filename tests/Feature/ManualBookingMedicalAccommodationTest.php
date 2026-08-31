<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\MedicalAccommodationTariff;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingStayExtensionService;
use App\Services\CancellationRequestService;
use App\Services\ManualBookingService;
use App\Services\RefundPolicyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingMedicalAccommodationTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;

    private RoomType $roomType;

    private RoomRate $roomRate;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        Storage::fake('public');

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 4,
            'room_count'       => 5,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'        => true,
        ]);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000099',
        ]);
        $this->adminUser->assignRole('super_admin');

        MedicalAccommodationTariff::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->update([
                'nightly_rate'           => 1_000_000,
                'companion_nightly_rate' => 500_000,
            ]);
        MedicalAccommodationTariff::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', 'other_veteran')
            ->update([
                'companions_included' => 0,
                'max_companions'      => 1,
            ]);
    }

    public function test_payment_step_shows_medical_accommodation_option(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز درمانی')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep')
            ->assertSet('step', 3)
            ->assertSee('اسکان درمانی')
            ->assertSee('نقدی')
            ->assertSee('کارتخوان');
    }

    public function test_medical_accommodation_allows_missing_referral_letter(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز درمانی')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_MEDICAL_ACCOMMODATION)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4);
    }

    public function test_medical_accommodation_livewire_flow_registers_regular_guest_without_discount(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->assertSet('veteranType', 'veteran_70_spouses')
            ->set('guestContactName', 'جانباز درمانی')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep');

        $veteranPricing = $component->get('pricingPreview');
        $this->assertSame(70, $veteranPricing['accommodation_discount_percentage']);
        $this->assertSame(600_000, $veteranPricing['total_price']);

        $component
            ->set('paymentMethod', Booking::PAYMENT_MEDICAL_ACCOMMODATION)
            ->assertSet('discountPct', 0)
            ->assertSet('selectedVeteranTypes', ['veteran_70_spouses'])
            ->assertSee('اسکان درمانی')
            ->assertDontSee('شامل تخفیف ایثارگری')
            ->assertDontSee('تخفیف دستی اقامت');

        $this->assertNotSame('عادی', $component->instance()->assignedVeteranGroupLabel());
        $this->assertStringContainsString('۷۰', $component->instance()->assignedVeteranGroupLabel());

        $medicalPricing = $component->get('pricingPreview');
        $this->assertSame(0, $medicalPricing['accommodation_discount_percentage']);
        $this->assertSame(0, $medicalPricing['veteran_accommodation_discount_amount'] ?? 0);
        $this->assertSame(2_000_000, $medicalPricing['total_price']);
        $this->assertSame([], $medicalPricing['veteran_accommodation_group_usage'] ?? []);

        $component
            ->set('medicalReferralLetter', [$this->referralLetter()])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertNotNull($booking);
        $this->assertTrue($booking->isMedicalAccommodation());
        $this->assertNotNull($booking->medical_contract_id);
        $this->assertNotEmpty($booking->medicalContractNumber());
        $this->assertSame(Booking::PAYMENT_MEDICAL_ACCOMMODATION, $booking->payment_method);
        $this->assertNull($booking->veteran_type_applied);
        $this->assertNull($booking->secondary_veteran_type_applied);
        $this->assertSame(0, (int) $booking->discount_percentage);
        $this->assertSame(0, (int) $booking->discount_amount);
        $this->assertSame(2_000_000, (int) $booking->total_price);
        $this->assertSame('اسکان درمانی — ' . $this->firstTariff()->label, $booking->veteranDiscountLabel());
        $this->assertSame(0, (int) $booking->guestPayableAmount());
        $this->assertSame(2_000_000, (int) $booking->employer_debt_amount);
        $this->assertNotNull($booking->program_employer_id);
        $this->assertTrue(\App\Support\MedicalAccommodationTariffs::isEmployerName($booking->employer?->name));
        $this->assertNotEmpty($booking->medicalReferralLetterPaths());
        Storage::disk('public')->assertExists($booking->medicalReferralLetterPaths()[0]);

        $guestUser = User::where('national_id', '4440123456')->first();
        $this->assertNotNull($guestUser);
        $this->assertTrue($guestUser->hasRole('guest'));
        $this->assertSame('veteran_70_spouses', $guestUser->veteran_type);
        $this->assertSame(70, (int) $guestUser->discount_percentage);

        $usage = $this->veteranPolicyFor($this->accommodation)->checkAccommodationUsage(
            'veteran_70_spouses',
            1,
            2,
            $guestUser->national_id,
            $guestUser->id,
        );
        $this->assertSame(0, $usage['used_in_period']);
    }

    public function test_host_ticked_veteran_group_is_saved_on_medical_guest_without_pricing_or_quota(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '6660123456')
            ->call('verifyBooker')
            ->assertSet('veteranType', '')
            ->set('selectedVeteranTypes', ['veteran_70_spouses'])
            ->assertSet('veteranType', 'veteran_70_spouses')
            ->set('guestContactName', 'جانباز تیک‌خورده')
            ->set('guestContactMobile', '09166601234')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_MEDICAL_ACCOMMODATION)
            ->assertSet('discountPct', 0)
            ->assertSet('selectedVeteranTypes', ['veteran_70_spouses']);

        $this->assertSame(2_000_000, $component->get('pricingPreview')['total_price']);
        $this->assertSame(0, $component->get('pricingPreview')['accommodation_discount_percentage']);
        $this->assertNotSame('عادی', $component->instance()->assignedVeteranGroupLabel());

        $component
            ->set('medicalReferralLetter', [$this->referralLetter()])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertNotNull($booking);
        $this->assertTrue($booking->isMedicalAccommodation());
        $this->assertNull($booking->veteran_type_applied);
        $this->assertSame(0, (int) $booking->discount_percentage);
        $this->assertSame(2_000_000, (int) $booking->total_price);
        $this->assertSame([], $booking->veteran_accommodation_group_usage ?? []);

        $guestUser = User::where('national_id', '6660123456')->first();
        $this->assertNotNull($guestUser);
        $this->assertSame('veteran_70_spouses', $guestUser->veteran_type);
        $this->assertSame(70, (int) $guestUser->discount_percentage);

        $usage = $this->veteranPolicyFor($this->accommodation)->checkAccommodationUsage(
            'veteran_70_spouses',
            1,
            2,
            $guestUser->national_id,
            $guestUser->id,
        );
        $this->assertSame(0, $usage['used_in_period']);
    }

    public function test_switching_back_to_cash_restores_veteran_pricing(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123777')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز تست')
            ->set('guestContactMobile', '09144401377')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_MEDICAL_ACCOMMODATION);

        $this->assertSame(2_000_000, $component->get('pricingPreview')['total_price']);

        $component->set('paymentMethod', 'cash');

        $pricing = $component->get('pricingPreview');
        $this->assertSame(70, $pricing['accommodation_discount_percentage']);
        $this->assertSame(600_000, $pricing['total_price']);
    }

    public function test_existing_veteran_profile_is_preserved_and_quota_is_not_consumed(): void
    {
        $guest = User::create([
            'name'                => 'جانباز موجود',
            'mobile'              => '09145556677',
            'national_id'         => '4555666778',
            'veteran_type'        => 'veteran_70_spouses',
            'discount_percentage' => 70,
        ]);
        $guest->assignRole('guest');

        $booking = $this->createMedicalBooking([
            'booker_national_id'   => $guest->national_id,
            'guest_contact_name'   => $guest->name,
            'guest_contact_mobile' => $guest->mobile,
            'user_id'              => $guest->id,
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 2,
        ]);

        $guest->refresh();
        $this->assertSame('veteran_70_spouses', $guest->veteran_type);
        $this->assertSame(70, (int) $guest->discount_percentage);

        $this->assertTrue($booking->isMedicalAccommodation());
        $this->assertNull($booking->veteran_type_applied);
        $this->assertSame(2_000_000, (int) $booking->total_price);

        $usage = $this->veteranPolicyFor($this->accommodation)->checkAccommodationUsage(
            'veteran_70_spouses',
            1,
            2,
            $guest->national_id,
            $guest->id,
        );

        $this->assertSame(0, $usage['used_in_period']);
        $this->assertSame(2, $usage['discounted_nights']);
    }

    public function test_existing_veteran_profile_is_preserved_when_medical_payload_omits_groups(): void
    {
        $guest = User::create([
            'name'                => 'جانباز بدون گروه در payload',
            'mobile'              => '09145556688',
            'national_id'         => '4555666889',
            'veteran_type'        => 'veteran_70_spouses',
            'discount_percentage' => 70,
        ]);
        $guest->assignRole('guest');

        $booking = $this->createMedicalBooking([
            'booker_national_id'   => $guest->national_id,
            'guest_contact_name'   => $guest->name,
            'guest_contact_mobile' => $guest->mobile,
            'user_id'              => $guest->id,
            'nights'               => 2,
        ]);

        $guest->refresh();
        $this->assertSame('veteran_70_spouses', $guest->veteran_type);
        $this->assertSame(70, (int) $guest->discount_percentage);
        $this->assertNull($booking->veteran_type_applied);
        $this->assertSame(2_000_000, (int) $booking->total_price);
    }

    public function test_medical_booking_does_not_reduce_later_veteran_discount_nights(): void
    {
        $nationalId = '4666777889';
        $mobile = '09146667788';

        $this->createMedicalBooking([
            'booker_national_id'   => $nationalId,
            'guest_contact_name'   => 'مهمان درمانی',
            'guest_contact_mobile' => $mobile,
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 3,
        ]);

        $guest = User::where('national_id', $nationalId)->first();
        $this->assertNotNull($guest);
        $this->assertSame('veteran_70_spouses', $guest->veteran_type);
        $this->assertSame(70, (int) $guest->discount_percentage);

        $cashBooking = app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [$this->roomLine(1)],
            'check_in'             => now()->addDays(20)->format('Y-m-d'),
            'check_out'            => now()->addDays(22)->format('Y-m-d'),
            'guests'               => 1,
            'children_under_6'     => 0,
            'extra_guests'         => 0,
            'veteran_type'         => 'veteran_70_spouses',
            'veteran_types'        => ['veteran_70_spouses'],
            'booker_national_id'   => $nationalId,
            'payment_method'       => 'cash',
            'user_id'              => $guest->id,
            'guest_contact_name'   => 'مهمان درمانی',
            'guest_contact_mobile' => $mobile,
            'guest_details'        => [[
                'full_name' => 'مهمان درمانی',
                'national_id' => $nationalId,
                'mobile' => $mobile,
                'relation' => 'رزرو‌کننده',
            ]],
        ], $this->adminUser);

        $this->assertSame('veteran_70_spouses', $cashBooking->veteran_type_applied);
        $this->assertSame(70, (int) $cashBooking->discount_percentage);
        $this->assertSame(600_000, (int) $cashBooking->total_price);
        $this->assertSame(2, (int) ($cashBooking->veteran_accommodation_group_usage['veteran_70_spouses'] ?? 0));
    }

    public function test_medical_booking_services_do_not_use_free_quota_or_discount(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $nationalId = '4777888990';
        $mobile = '09147778899';

        $booking = $this->createMedicalBooking([
            'booker_national_id'   => $nationalId,
            'guest_contact_name'   => 'مهمان خدمات',
            'guest_contact_mobile' => $mobile,
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
            'services'             => [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 5,
            ]],
        ]);

        $service = $booking->services->first();
        $this->assertNotNull($service);
        $this->assertTrue((bool) $service->excluded_from_veteran_quota);
        $this->assertSame(0, (int) $service->free_units);
        $this->assertSame(0, (int) $service->discount_amount);
        $this->assertSame(500_000, (int) $service->total);
        $this->assertSame(1_500_000, (int) $booking->total_price);

        $used = $this->veteranPolicyFor($this->accommodation)->usedServiceSessionsInWeek(
            'veteran_70_spouses',
            $nationalId,
            $booking->user_id,
            $pool->id,
            $booking->check_in->format('Y-m-d'),
        );
        $this->assertSame(0, $used);

        $guest = User::where('national_id', $nationalId)->first();
        $cashBooking = app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [$this->roomLine(1)],
            'check_in'             => $booking->check_in->format('Y-m-d'),
            'check_out'            => $booking->check_out->format('Y-m-d'),
            'guests'               => 1,
            'children_under_6'     => 0,
            'extra_guests'         => 0,
            'veteran_type'         => 'veteran_70_spouses',
            'veteran_types'        => ['veteran_70_spouses'],
            'booker_national_id'   => $nationalId,
            'payment_method'       => 'cash',
            'user_id'              => $guest->id,
            'guest_contact_name'   => 'مهمان خدمات',
            'guest_contact_mobile' => $mobile,
            'services'             => [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 100_000,
                'quantity'           => 5,
            ]],
            'guest_details'        => [[
                'full_name' => 'مهمان خدمات',
                'national_id' => $nationalId,
                'mobile' => $mobile,
                'relation' => 'رزرو‌کننده',
            ]],
        ], $this->adminUser);

        $cashService = $cashBooking->services->first();
        $this->assertSame(3, (int) $cashService->free_units);
    }

    public function test_manual_guest_and_service_discounts_are_stripped_for_medical_booking(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4888999001',
            'guest_contact_name'   => 'مهمان تخفیف دستی',
            'guest_contact_mobile' => '09148889990',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
            'guest_details'        => [[
                'full_name' => 'مهمان تخفیف دستی',
                'national_id' => '4888999001',
                'mobile' => '09148889990',
                'relation' => 'رزرو‌کننده',
                'excluded_from_veteran_discount' => true,
                'manual_discount_percentage' => 40,
                'manual_discount_reason' => 'نباید اعمال شود',
            ]],
            'services' => [[
                'name' => 'خدمت سفارشی',
                'unit_price' => 200_000,
                'quantity' => 1,
                'excluded_from_veteran_quota' => true,
                'manual_discount_percentage' => 50,
                'manual_discount_reason' => 'نباید اعمال شود',
            ]],
        ]);

        $guest = $booking->guestDetails()->first();
        $this->assertNotNull($guest);
        $this->assertNull($guest->manual_discount_percentage);
        $this->assertFalse((bool) $guest->excluded_from_veteran_discount);

        $service = $booking->services->first();
        $this->assertSame(0, (int) $service->discount_amount);
        $this->assertNull($service->manual_discount_percentage);
        $this->assertSame(1_200_000, (int) $booking->total_price);
    }

    public function test_recalculate_totals_keeps_medical_booking_at_full_rate(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4999000112',
            'guest_contact_name'   => 'مهمان تمدید',
            'guest_contact_mobile' => '09149990001',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 2,
        ]);

        $booking->update([
            'veteran_type_applied' => 'veteran_70_spouses',
            'discount_percentage'  => 70,
        ]);

        app(ManualBookingService::class)->recalculateTotals($booking->fresh());

        $booking->refresh();
        $this->assertTrue($booking->isMedicalAccommodation());
        $this->assertSame(2_000_000, (int) $booking->total_price);
        $this->assertSame(0, (int) $booking->discount_amount);
    }

    public function test_dual_veteran_groups_are_ignored_for_medical_accommodation(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4111222334',
            'guest_contact_name'   => 'دو گروهی',
            'guest_contact_mobile' => '09141112223',
            'veteran_types'        => ['veteran_70_spouses', 'martyr_children'],
            'nights'               => 2,
        ]);

        $this->assertNull($booking->veteran_type_applied);
        $this->assertNull($booking->secondary_veteran_type_applied);
        $this->assertSame(2_000_000, (int) $booking->total_price);
        $this->assertSame([], $booking->veteran_accommodation_group_usage ?? []);

        $guest = User::where('national_id', '4111222334')->first();
        $this->assertNotNull($guest);
        $this->assertSame('veteran_70_spouses', $guest->veteran_type);
        $this->assertSame('martyr_children', $guest->secondary_veteran_type);
        $this->assertSame(70, (int) $guest->discount_percentage);
    }

    public function test_service_create_without_letter_succeeds(): void
    {
        $booking = app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [$this->roomLine(1)],
            'check_in'             => now()->addDays(10)->format('Y-m-d'),
            'check_out'            => now()->addDays(12)->format('Y-m-d'),
            'guests'               => 1,
            'payment_method'       => Booking::PAYMENT_MEDICAL_ACCOMMODATION,
            'booker_national_id'   => '4222333445',
            'guest_contact_name'   => 'بدون نامه',
            'guest_contact_mobile' => '09142223334',
            'guest_details'        => [[
                'full_name' => 'بدون نامه',
                'national_id' => '4222333445',
                'mobile' => '09142223334',
            ]],
        ], $this->adminUser);

        $this->assertTrue($booking->isMedicalAccommodation());
        $this->assertNotNull($booking->medical_contract_id);
        $this->assertFalse($booking->hasMedicalReferralLetters());
    }

    public function test_admin_can_download_medical_referral_letter(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4333444556',
            'guest_contact_name'   => 'دانلود معرفی‌نامه',
            'guest_contact_mobile' => '09143334445',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
        ]);

        $url = $booking->medicalReferralLetterUrl('admin');
        $this->assertStringContainsString('/admin/bookings/' . $booking->id . '/medical-referral', $url);
        $this->assertStringNotContainsString('/storage/', $url);

        $extension = strtolower(pathinfo($booking->medicalReferralLetterPaths()[0], PATHINFO_EXTENSION) ?: 'bin');

        $this->actingAs($this->adminUser)
            ->get($url)
            ->assertOk()
            ->assertDownload('medical-referral-' . $booking->tracking_code . '.' . $extension);
    }

    public function test_medical_booking_can_store_multiple_referral_letters(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4555666778',
            'guest_contact_name'   => 'چند فایل',
            'guest_contact_mobile' => '09145556667',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
            'medical_referral_letter' => [
                $this->referralLetter(),
                UploadedFile::fake()->create('referral-letter-2.pdf', 120, 'application/pdf'),
            ],
        ]);

        $paths = $booking->medicalReferralLetterPaths();
        $this->assertCount(2, $paths);
        Storage::disk('public')->assertExists($paths[0]);
        Storage::disk('public')->assertExists($paths[1]);

        $secondUrl = $booking->medicalReferralLetterUrl('admin', 1);
        $this->assertStringContainsString('index=1', $secondUrl);

        $this->actingAs($this->adminUser)
            ->get($secondUrl)
            ->assertOk()
            ->assertDownload('medical-referral-' . $booking->tracking_code . '-2.pdf');
    }

    public function test_unrelated_user_cannot_download_medical_referral_letter(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4444555667',
            'guest_contact_name'   => 'بدون دسترسی',
            'guest_contact_mobile' => '09144445556',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
        ]);

        $stranger = User::create([
            'name'   => 'غریبه',
            'mobile' => '09140000000',
        ]);
        $stranger->assignRole('guest');

        $this->actingAs($stranger)
            ->get(route('bookings.medical-referral', $booking))
            ->assertForbidden();
    }

    public function test_booking_owner_can_download_medical_referral_letter(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4555666778',
            'guest_contact_name'   => 'مالک رزرو',
            'guest_contact_mobile' => '09145556667',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
        ]);

        $owner = $booking->user;
        $this->assertNotNull($owner);

        $this->actingAs($owner)
            ->get(route('bookings.medical-referral', $booking))
            ->assertOk()
            ->assertDownload();
    }

    public function test_medical_booking_uses_selected_tariff_not_room_rate(): void
    {
        $tariff = MedicalAccommodationTariff::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->where('key', 'other_veteran')
            ->firstOrFail();
        $tariff->update([
            'nightly_rate'           => 3_500_000,
            'companion_nightly_rate' => 1_500_000,
            'companions_included'    => 0,
            'max_companions'         => 1,
            'is_active'              => true,
        ]);

        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4666777880',
            'guest_contact_name'   => 'سایر ایثارگران',
            'guest_contact_mobile' => '09146667780',
            'nights'               => 2,
            'medical_tariff_id'    => $tariff->id,
            'guest_details'        => [
                ['full_name' => 'بیمار', 'national_id' => '4666777880', 'mobile' => '09146667780'],
                ['full_name' => 'همراه', 'national_id' => '4666777881', 'mobile' => '09146667781'],
            ],
        ]);

        $this->assertSame(1, (int) $booking->medical_companion_count);
        $this->assertSame(7_000_000, (int) ($booking->medical_tariff_snapshot['patient_total'] ?? 0));
        $this->assertSame(3_000_000, (int) ($booking->medical_tariff_snapshot['companion_total'] ?? 0));
        $this->assertSame(10_000_000, (int) $booking->total_price);
        $this->assertSame(10_000_000, (int) $booking->employer_debt_amount);
        $this->assertSame(0, (int) $booking->guestPayableAmount());
    }

    public function test_medical_companion_over_limit_is_rejected(): void
    {
        $tariff = $this->firstTariff();
        $tariff->update(['max_companions' => 1, 'companions_included' => 1]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('حداکثر');

        $this->createMedicalBooking([
            'booker_national_id'   => '4777888991',
            'guest_contact_name'   => 'سقف همراه',
            'guest_contact_mobile' => '09147778891',
            'nights'               => 1,
            'medical_tariff_id'    => $tariff->id,
            'guest_details'        => [
                ['full_name' => 'بیمار', 'national_id' => '4777888991', 'mobile' => '09147778891'],
                ['full_name' => 'همراه۱', 'national_id' => '4777888992', 'mobile' => '09147778892'],
                ['full_name' => 'همراه۲', 'national_id' => '4777888993', 'mobile' => '09147778893'],
            ],
        ]);
    }

    public function test_medical_cancellation_has_no_penalty_and_zeros_guest_refund(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4888999002',
            'guest_contact_name'   => 'کنسل درمانی',
            'guest_contact_mobile' => '09148889902',
            'nights'               => 3,
        ]);

        $preview = app(RefundPolicyService::class)->previewForBooking($booking);
        $this->assertSame(100, $preview['percentage']);
        $this->assertSame(0, $preview['amount']);
        $this->assertFalse($preview['guest_paid']);
        $this->assertSame(0, $preview['employer_debt_after']);

        $reason = \App\Models\CancellationReason::query()
            ->forAccommodation($this->accommodation->id)
            ->where('is_custom', false)
            ->firstOrFail();

        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '',
            'refund_amount'          => 0,
        ], $this->adminUser);

        $this->assertSame(0, (int) $request->refund_amount);
        $this->assertSame(100, (int) $request->refund_percentage);

        app(CancellationRequestService::class)->approve($request, $this->adminUser);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(0, (int) $booking->fresh()->employer_debt_amount);
    }

    public function test_medical_mid_stay_cancellation_keeps_used_nights_as_employer_debt(): void
    {
        $checkIn = now()->subDays(2)->format('Y-m-d');
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4888999003',
            'guest_contact_name'   => 'خروج زودتر',
            'guest_contact_mobile' => '09148889903',
            'check_in'             => $checkIn,
            'nights'               => 4,
        ]);

        $this->assertSame(4_000_000, (int) $booking->total_price);

        $preview = app(RefundPolicyService::class)->previewForBooking($booking);
        $this->assertTrue($preview['is_mid_stay']);
        $this->assertSame(2, $preview['nights_elapsed']);
        $this->assertSame(2_000_000, $preview['employer_debt_after']);
        $this->assertSame(0, $preview['amount']);

        $reason = \App\Models\CancellationReason::query()
            ->forAccommodation($this->accommodation->id)
            ->where('is_custom', false)
            ->firstOrFail();

        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '',
            'refund_amount'          => 0,
        ], $this->adminUser);

        app(CancellationRequestService::class)->approve($request, $this->adminUser);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(2_000_000, (int) $booking->fresh()->employer_debt_amount);
    }

    public function test_medical_stay_can_be_shortened_without_penalty(): void
    {
        $booking = $this->createMedicalBooking([
            'booker_national_id'   => '4999000113',
            'guest_contact_name'   => 'کاهش اقامت',
            'guest_contact_mobile' => '09149990013',
            'nights'               => 4,
        ]);

        $this->assertSame(4_000_000, (int) $booking->total_price);

        $newCheckOut = $booking->check_in->copy()->addDays(2)->format('Y-m-d');
        $updated = app(BookingStayExtensionService::class)->extendCheckout($booking, $newCheckOut);

        $this->assertSame(2, (int) $updated->nights);
        $this->assertSame(2_000_000, (int) $updated->total_price);
        $this->assertSame(2_000_000, (int) $updated->employer_debt_amount);
    }

    public function test_regular_booking_cannot_shorten_checkout_without_cancellation(): void
    {
        $booking = app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [$this->roomLine(1)],
            'check_in'             => now()->addDays(10)->format('Y-m-d'),
            'check_out'            => now()->addDays(14)->format('Y-m-d'),
            'guests'               => 1,
            'payment_method'       => 'cash',
            'booker_national_id'   => '4111222335',
            'guest_contact_name'   => 'عادی',
            'guest_contact_mobile' => '09141112235',
            'guest_details'        => [[
                'full_name' => 'عادی',
                'national_id' => '4111222335',
                'mobile' => '09141112235',
            ]],
        ], $this->adminUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('اسکان درمانی');

        app(BookingStayExtensionService::class)->extendCheckout(
            $booking,
            $booking->check_in->copy()->addDays(2)->format('Y-m-d'),
        );
    }

    public function test_province_settings_page_loads_for_admin(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.accommodations.medical-accommodation', $this->accommodation))
            ->assertOk()
            ->assertSee('اسکان درمانی')
            ->assertSee('جانبازان معزز گردنی')
            ->assertSee('بیمه دی')
            ->assertSee('شماره قرارداد')
            ->assertSee('کارفرما در لیست نیست؟ افزودن');
    }

    public function test_admin_can_update_medical_tariff_from_settings(): void
    {
        $tariff = $this->firstTariff();

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->assertSee('جانبازان معزز گردنی')
            ->assertSet('contracts.0.number_locked', true)
            ->set('contracts.0.tariffs.0.nightly_rate', 12_000_000)
            ->call('saveContract', 0)
            ->assertHasNoErrors();

        $this->assertSame(12_000_000, (int) $tariff->fresh()->nightly_rate);
    }

    public function test_admin_can_add_employer_from_medical_settings(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Admin\AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->call('openEmployerModal')
            ->assertSet('showAddEmployer', true)
            ->set('newEmployerName', 'بیمه ایران')
            ->set('newEmployerNationalId', '3344556677')
            ->set('newEmployerMobile', '09127778899')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors()
            ->assertSet('showAddEmployer', false);

        $employer = \App\Models\ProgramEmployer::query()->where('name', 'بیمه ایران')->first();
        $this->assertNotNull($employer);
        $component->assertSet('programEmployerId', (string) $employer->id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createMedicalBooking(array $overrides): Booking
    {
        $nights = (int) ($overrides['nights'] ?? 2);
        $checkIn = $overrides['check_in'] ?? now()->addDays(10)->format('Y-m-d');
        $checkOut = $overrides['check_out'] ?? Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');
        $nationalId = $overrides['booker_national_id'];
        $mobile = $overrides['guest_contact_mobile'];
        $name = $overrides['guest_contact_name'];
        $guests = (int) ($overrides['guests'] ?? count($overrides['guest_details'] ?? []) ?: 1);
        $guests = max(1, $guests);

        $payload = [
            'room_lines' => [$this->roomLine($guests)],
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'guests'               => $guests,
            'children_under_6'     => 0,
            'extra_guests'         => 0,
            'veteran_type'         => $overrides['veteran_types'][0] ?? null,
            'veteran_types'        => $overrides['veteran_types'] ?? [],
            'booker_national_id'   => $nationalId,
            'payment_method'       => Booking::PAYMENT_MEDICAL_ACCOMMODATION,
            'is_medical_accommodation' => true,
            'medical_contract_id'  => $overrides['medical_contract_id'] ?? null,
            'medical_tariff_id'    => $overrides['medical_tariff_id'] ?? $this->firstTariff()->id,
            'medical_referral_letter' => $overrides['medical_referral_letter'] ?? $this->referralLetter(),
            'user_id'              => $overrides['user_id'] ?? null,
            'guest_contact_name'   => $name,
            'guest_contact_mobile' => $mobile,
            'services'             => $overrides['services'] ?? [],
            'guest_details'        => $overrides['guest_details'] ?? [[
                'full_name' => $name,
                'national_id' => $nationalId,
                'mobile' => $mobile,
                'relation' => 'رزرو‌کننده',
            ]],
        ];

        return app(ManualBookingService::class)->create($this->accommodation, $payload, $this->adminUser);
    }

    private function firstTariff(): MedicalAccommodationTariff
    {
        return MedicalAccommodationTariff::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->active()
            ->ordered()
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function roomLine(int $guests): array
    {
        return [
            'room_type_id'     => $this->roomType->id,
            'room_rate_id'     => $this->roomRate->id,
            'adults'           => $guests,
            'children_under_6' => 0,
            'guests'           => $guests,
            'extra_guests'     => 0,
            'bill_full_rooms'  => false,
        ];
    }

    private function referralLetter(): UploadedFile
    {
        return UploadedFile::fake()->create('referral-letter.pdf', 120, 'application/pdf');
    }

    /** @return array{accommodation:\App\Models\Accommodation, panel:string} */
    private function formParams(): array
    {
        return [
            'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
            'panel'         => 'admin',
        ];
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');

        return [$checkIn, $checkOut];
    }
}
