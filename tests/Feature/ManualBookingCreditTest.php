<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ManualBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingCreditTest extends TestCase
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
            'mobile' => '09000000088',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_payment_step_shows_credit_option(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '5550123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'مهمان اعتباری')
            ->set('guestContactMobile', '09155501234')
            ->call('nextStep')
            ->assertSet('step', 3)
            ->assertSee('اعتباری')
            ->assertSee('اسکان درمانی')
            ->assertSee('نقدی')
            ->assertSee('کارتخوان');
    }

    public function test_credit_payment_allows_missing_letter(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '5550123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'مهمان اعتباری')
            ->set('guestContactMobile', '09155501234')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_CREDIT)
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('step', 4);
    }

    public function test_credit_livewire_flow_registers_regular_guest_without_discount(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->assertSet('veteranType', 'veteran_70_spouses')
            ->set('guestContactName', 'مهمان اعتباری')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep');

        $veteranPricing = $component->get('pricingPreview');
        $this->assertSame(70, $veteranPricing['accommodation_discount_percentage']);
        $this->assertSame(600_000, $veteranPricing['total_price']);

        $component
            ->set('paymentMethod', Booking::PAYMENT_CREDIT)
            ->assertSet('discountPct', 0)
            ->assertSet('selectedVeteranTypes', ['veteran_70_spouses'])
            ->assertSee('اعتباری — نرخ عادی')
            ->assertSee('پرداخت اعتباری — مهمان عادی بدون تخفیف')
            ->assertDontSee('شامل تخفیف ایثارگری')
            ->assertDontSee('تخفیف دستی اقامت');

        $this->assertNotSame('عادی', $component->instance()->assignedVeteranGroupLabel());
        $this->assertStringContainsString('۷۰', $component->instance()->assignedVeteranGroupLabel());

        $creditPricing = $component->get('pricingPreview');
        $this->assertSame(0, $creditPricing['accommodation_discount_percentage']);
        $this->assertSame(0, $creditPricing['veteran_accommodation_discount_amount'] ?? 0);
        $this->assertSame(2_000_000, $creditPricing['total_price']);
        $this->assertSame([], $creditPricing['veteran_accommodation_group_usage'] ?? []);

        $component
            ->set('creditLetter', [$this->creditLetter()])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertNotNull($booking);
        $this->assertTrue($booking->isCredit());
        $this->assertTrue($booking->billsAsRegularGuest());
        $this->assertFalse($booking->isMedicalAccommodation());
        $this->assertSame(Booking::PAYMENT_CREDIT, $booking->payment_method);
        $this->assertNull($booking->veteran_type_applied);
        $this->assertNull($booking->secondary_veteran_type_applied);
        $this->assertSame(0, (int) $booking->discount_percentage);
        $this->assertSame(0, (int) $booking->discount_amount);
        $this->assertSame(2_000_000, (int) $booking->total_price);
        $this->assertSame('اعتباری', $booking->paymentMethodLabel());
        $this->assertSame('اعتباری (مهمان عادی)', $booking->veteranDiscountLabel());
        $this->assertNotEmpty($booking->creditLetterPaths());
        Storage::disk('public')->assertExists($booking->creditLetterPaths()[0]);

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

    public function test_switching_from_medical_to_credit_stays_at_full_rate(): void
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

        $this->assertSame(20_000_000, $component->get('pricingPreview')['total_price']);

        $component->set('paymentMethod', Booking::PAYMENT_CREDIT);

        $pricing = $component->get('pricingPreview');
        $this->assertSame(0, $pricing['accommodation_discount_percentage']);
        $this->assertSame(2_000_000, $pricing['total_price']);
    }

    public function test_switching_from_credit_to_cash_restores_veteran_pricing(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123888')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز تست')
            ->set('guestContactMobile', '09144401388')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_CREDIT);

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
            'mobile'              => '09155556677',
            'national_id'         => '5555666778',
            'veteran_type'        => 'veteran_70_spouses',
            'discount_percentage' => 70,
        ]);
        $guest->assignRole('guest');

        $booking = $this->createCreditBooking([
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

        $this->assertTrue($booking->isCredit());
        $this->assertTrue($booking->billsAsRegularGuest());
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

    public function test_existing_veteran_profile_is_preserved_when_credit_payload_omits_groups(): void
    {
        $guest = User::create([
            'name'                => 'جانباز بدون گروه در payload',
            'mobile'              => '09155556688',
            'national_id'         => '5555666889',
            'veteran_type'        => 'veteran_70_spouses',
            'discount_percentage' => 70,
        ]);
        $guest->assignRole('guest');

        $booking = $this->createCreditBooking([
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

    public function test_credit_booking_does_not_reduce_later_veteran_discount_nights(): void
    {
        $nationalId = '5666777889';
        $mobile = '09156667788';

        $this->createCreditBooking([
            'booker_national_id'   => $nationalId,
            'guest_contact_name'   => 'مهمان اعتباری',
            'guest_contact_mobile' => $mobile,
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 3,
        ]);

        $guest = User::where('national_id', $nationalId)->first();
        $this->assertNotNull($guest);

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
            'guest_contact_name'   => 'مهمان اعتباری',
            'guest_contact_mobile' => $mobile,
            'guest_details'        => [[
                'full_name' => 'مهمان اعتباری',
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

    public function test_credit_booking_services_do_not_use_free_quota_or_discount(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $nationalId = '5777888990';
        $mobile = '09157778899';

        $booking = $this->createCreditBooking([
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

    public function test_manual_guest_and_service_discounts_are_stripped_for_credit_booking(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5888999001',
            'guest_contact_name'   => 'مهمان تخفیف دستی',
            'guest_contact_mobile' => '09158889990',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
            'guest_details'        => [[
                'full_name' => 'مهمان تخفیف دستی',
                'national_id' => '5888999001',
                'mobile' => '09158889990',
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

    public function test_recalculate_totals_keeps_credit_booking_at_full_rate(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5999000112',
            'guest_contact_name'   => 'مهمان تمدید',
            'guest_contact_mobile' => '09159990001',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 2,
        ]);

        $booking->update([
            'veteran_type_applied' => 'veteran_70_spouses',
            'discount_percentage'  => 70,
        ]);

        app(ManualBookingService::class)->recalculateTotals($booking->fresh());

        $booking->refresh();
        $this->assertTrue($booking->isCredit());
        $this->assertSame(2_000_000, (int) $booking->total_price);
        $this->assertSame(0, (int) $booking->discount_amount);
        $this->assertSame(0, (int) $booking->discount_percentage);
        $this->assertNull($booking->veteran_type_applied);
    }

    public function test_dual_veteran_groups_are_ignored_for_credit_booking(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5111222334',
            'guest_contact_name'   => 'دو گروهی',
            'guest_contact_mobile' => '09151112223',
            'veteran_types'        => ['veteran_70_spouses', 'martyr_children'],
            'nights'               => 2,
        ]);

        $this->assertNull($booking->veteran_type_applied);
        $this->assertNull($booking->secondary_veteran_type_applied);
        $this->assertSame(2_000_000, (int) $booking->total_price);
        $this->assertSame([], $booking->veteran_accommodation_group_usage ?? []);

        $guest = User::where('national_id', '5111222334')->first();
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
            'payment_method'       => Booking::PAYMENT_CREDIT,
            'booker_national_id'   => '5222333445',
            'guest_contact_name'   => 'بدون نامه',
            'guest_contact_mobile' => '09152223334',
            'guest_details'        => [[
                'full_name' => 'بدون نامه',
                'national_id' => '5222333445',
                'mobile' => '09152223334',
            ]],
        ], $this->adminUser);

        $this->assertTrue($booking->isCredit());
        $this->assertFalse($booking->hasCreditLetters());
    }

    public function test_admin_can_download_credit_letter(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5333444556',
            'guest_contact_name'   => 'دانلود معرفی‌نامه',
            'guest_contact_mobile' => '09153334445',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
        ]);

        $url = $booking->creditLetterUrl('admin');
        $this->assertStringContainsString('/admin/bookings/' . $booking->id . '/credit-letter', $url);
        $this->assertStringNotContainsString('/storage/', $url);

        $extension = strtolower(pathinfo($booking->creditLetterPaths()[0], PATHINFO_EXTENSION) ?: 'bin');

        $this->actingAs($this->adminUser)
            ->get($url)
            ->assertOk()
            ->assertDownload('credit-letter-' . $booking->tracking_code . '.' . $extension);
    }

    public function test_credit_booking_can_store_multiple_letters(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5555666778',
            'guest_contact_name'   => 'چند فایل',
            'guest_contact_mobile' => '09155556667',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
            'credit_letter'        => [
                $this->creditLetter(),
                UploadedFile::fake()->create('credit-letter-2.pdf', 120, 'application/pdf'),
            ],
        ]);

        $paths = $booking->creditLetterPaths();
        $this->assertCount(2, $paths);
        Storage::disk('public')->assertExists($paths[0]);
        Storage::disk('public')->assertExists($paths[1]);

        $secondUrl = $booking->creditLetterUrl('admin', 1);
        $this->assertStringContainsString('index=1', $secondUrl);

        $this->actingAs($this->adminUser)
            ->get($secondUrl)
            ->assertOk()
            ->assertDownload('credit-letter-' . $booking->tracking_code . '-2.pdf');
    }

    public function test_unrelated_user_cannot_download_credit_letter(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5444555667',
            'guest_contact_name'   => 'بدون دسترسی',
            'guest_contact_mobile' => '09154445556',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
        ]);

        $stranger = User::create([
            'name'   => 'غریبه',
            'mobile' => '09150000000',
        ]);
        $stranger->assignRole('guest');

        $this->actingAs($stranger)
            ->get(route('bookings.credit-letter', $booking))
            ->assertForbidden();
    }

    public function test_booking_owner_can_download_credit_letter(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5555666778',
            'guest_contact_name'   => 'مالک رزرو',
            'guest_contact_mobile' => '09155556667',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
        ]);

        $owner = $booking->user;
        $this->assertNotNull($owner);

        $this->actingAs($owner)
            ->get(route('bookings.credit-letter', $booking))
            ->assertOk()
            ->assertDownload();
    }

    public function test_credit_booking_shows_badge_and_document_on_details(): void
    {
        $booking = $this->createCreditBooking([
            'booker_national_id'   => '5666777889',
            'guest_contact_name'   => 'نمایش جزئیات',
            'guest_contact_mobile' => '09156667788',
            'veteran_types'        => ['veteran_70_spouses'],
            'nights'               => 1,
        ]);

        $this->assertSame('اعتباری (مهمان عادی)', $booking->veteranDiscountLabel());
        $this->assertTrue($booking->billsAsRegularGuest());

        $html = view('components.booking.list-guest-badges', ['booking' => $booking])->render();
        $this->assertStringContainsString('اعتباری', $html);
        $this->assertStringNotContainsString('% تخفیف', $html);

        $booking->load([
            'user', 'accommodation.city', 'roomType', 'roomRate',
            'guestDetails', 'bookingRooms.roomType', 'bookingRooms.room', 'createdBy',
        ]);

        $detailHtml = view('components.booking.show-details._detail-booking', [
            'booking' => $booking,
            'panel' => 'admin',
        ])->render();
        $this->assertStringContainsString('اعتباری', $detailHtml);
        $this->assertStringContainsString('معرفی‌نامه اعتباری', $detailHtml);
        $this->assertStringContainsString('credit-letter', $detailHtml);

        $notesHtml = view('components.booking.show-details._modal-notes', [
            'booking' => $booking,
            'panel' => 'admin',
        ])->render();
        $this->assertStringContainsString('معرفی‌نامه اعتباری', $notesHtml);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCreditBooking(array $overrides): Booking
    {
        $nights = (int) ($overrides['nights'] ?? 2);
        $checkIn = $overrides['check_in'] ?? now()->addDays(10)->format('Y-m-d');
        $checkOut = $overrides['check_out'] ?? Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');
        $nationalId = $overrides['booker_national_id'];
        $mobile = $overrides['guest_contact_mobile'];
        $name = $overrides['guest_contact_name'];

        $payload = [
            'room_lines' => [$this->roomLine(1)],
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'guests'               => 1,
            'children_under_6'     => 0,
            'extra_guests'         => 0,
            'veteran_type'         => $overrides['veteran_types'][0] ?? null,
            'veteran_types'        => $overrides['veteran_types'] ?? [],
            'booker_national_id'   => $nationalId,
            'payment_method'       => Booking::PAYMENT_CREDIT,
            'is_credit'            => true,
            'credit_letter'        => $overrides['credit_letter'] ?? $this->creditLetter(),
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

    private function creditLetter(): UploadedFile
    {
        return UploadedFile::fake()->create('credit-letter.pdf', 120, 'application/pdf');
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
