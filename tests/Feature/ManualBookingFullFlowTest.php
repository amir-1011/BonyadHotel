<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Services\BookingPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end Livewire manual booking flow: rooms → booker → payment (per-guest services) → submit.
 */
class ManualBookingFullFlowTest extends TestCase
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

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق چهار تخته',
            'capacity'         => 4,
            'room_count'       => 5,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000099',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_full_livewire_flow_with_veteran_guest_exclusion_at_payment_step(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 4, $this->roomType->id, $this->roomRate->id, 0, false, 0, 4)
            ->assertSet('totalGuests', 4)
            ->assertSet('step', 1)
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->assertSet('bookerVerified', true)
            ->assertSet('veteranType', 'veteran_70_spouses')
            ->set('guestContactName', 'جانباز تست')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep')
            ->assertSet('step', 3)
            ->assertCount('guestDetails', 4);

        $pricingAllEligible = $component->get('pricingPreview');
        $this->assertSame(8_000_000, $pricingAllEligible['room_subtotal']);
        $this->assertSame(2_400_000, $pricingAllEligible['total_price']);

        $component
            ->set('guestDetails.2.excluded_from_veteran_discount', true)
            ->set('guestDetails.3.excluded_from_veteran_discount', true)
            ->set('paymentMethod', 'card_terminal');

        $pricingPartial = $component->get('pricingPreview');
        $this->assertSame(2, $pricingPartial['non_veteran_discount_guests']);
        $this->assertSame(8_000_000, $pricingPartial['room_subtotal']);
        $this->assertSame(5_200_000, $pricingPartial['total_price']);

        $component
            ->call('submit')
            ->assertSet('step', 5)
            ->assertSet('createdBookingId', fn ($id) => $id !== null);

        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertNotNull($booking);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('manual', $booking->booking_source);
        $this->assertSame('card_terminal', $booking->payment_method);
        $this->assertSame('veteran_70_spouses', $booking->veteran_type_applied);
        $this->assertSame(70, $booking->discount_percentage);
        $this->assertSame(5_200_000, $booking->total_price);
        $this->assertSame(4, $booking->guests);

        $excluded = $booking->guestDetails()->where('excluded_from_veteran_discount', true)->count();
        $this->assertSame(2, $excluded);

        $booker = $booking->guestDetails()->where('sort_order', 0)->first();
        $this->assertFalse($booker->excluded_from_veteran_discount);
        $this->assertSame('4440123456', $booker->national_id);
        $this->assertSame('رزرو‌کننده', $booker->relation);

        $guestUser = User::where('national_id', '4440123456')->first();
        $this->assertNotNull($guestUser);
        $this->assertTrue($guestUser->hasRole('guest'));
    }

    public function test_dual_veteran_groups_with_mixed_guest_exclusions(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 3, $this->roomType->id, $this->roomRate->id, 0, false, 0, 3)
            ->call('nextStep')
            ->set('bookerNationalId', '4440555666')
            ->call('verifyBooker')
            ->set('selectedVeteranTypes', ['veteran_70_spouses', 'martyr_children'])
            ->set('guestContactName', 'مهمان دو گروهی')
            ->set('guestContactMobile', '09440555666')
            ->call('nextStep')
            ->assertSet('veteranType', 'veteran_70_spouses')
            ->assertSet('secondaryVeteranType', 'martyr_children')
            ->set('guestDetails.1.excluded_from_veteran_discount', true)
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertSame('veteran_70_spouses', $booking->veteran_type_applied);
        $this->assertSame('martyr_children', $booking->secondary_veteran_type_applied);
        $this->assertSame(70, $booking->discount_percentage);
        $this->assertSame(1, $booking->guestDetails()->where('excluded_from_veteran_discount', true)->count());

        $guestUser = User::where('national_id', '4440555666')->first();
        $this->assertNotNull($guestUser);
        $this->assertSame('veteran_70_spouses', $guestUser->veteran_type);
        $this->assertSame('martyr_children', $guestUser->secondary_veteran_type);
        $this->assertSame(70, $guestUser->discount_percentage);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'        => $checkIn,
            'check_out'       => $checkOut,
            'guests'          => 3,
            'veteran_types'   => ['veteran_70_spouses', 'martyr_children'],
            'accommodation'   => $this->accommodation,
            'room_type'       => $this->roomType,
            'room_rate'       => $this->roomRate,
            'non_veteran_discount_guests' => 1,
            'per_guest_slots' => app(BookingPricingService::class)->buildPerGuestSlotsFromGuestDetails(
                [
                    ['excluded_from_veteran_discount' => false],
                    ['excluded_from_veteran_discount' => true],
                    ['excluded_from_veteran_discount' => false],
                ],
                billingGuests: 3,
                childrenUnder6: 0,
                veteranType: 'veteran_70_spouses',
                veteranDiscountPct: 70,
            ),
        ]);

        $this->assertSame($pricing['total_price'], $booking->total_price);
    }

    public function test_excluded_guest_can_receive_manual_discount_at_payment_step(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '4440999888')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز با همراه عادی')
            ->set('guestContactMobile', '09144409998')
            ->call('nextStep')
            ->set('guestDetails.1.excluded_from_veteran_discount', true)
            ->set('guestDetails.1.manual_discount_percentage', '20')
            ->set('guestDetails.1.manual_discount_reason', 'همکاری ویژه')
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $guestTwo = $booking->guestDetails->firstWhere('sort_order', 1);
        $this->assertTrue($guestTwo->excluded_from_veteran_discount);
        $this->assertSame(20, $guestTwo->manual_discount_percentage);
        $this->assertSame('همکاری ویژه', $guestTwo->manual_discount_reason);
        $this->assertIsArray($booking->guest_discount_snapshot);
    }

    public function test_per_guest_pool_service_uses_veteran_quota_for_booker(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);
        [$pool, $variant] = $this->createPoolVariant(200_000);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز استخر')
            ->set('guestContactMobile', '09144401235')
            ->call('nextStep')
            ->set('guestDetails.0.services.0.service_catalog_id', (string) $pool->id)
            ->set('guestDetails.0.services.0.service_catalog_variant_id', (string) $variant->id)
            ->set('guestDetails.0.services.0.quantity', 3)
            ->call('confirmGuestService', 0, 0)
            ->set('guestDetails.1.services.0.service_catalog_id', (string) $pool->id)
            ->set('guestDetails.1.services.0.service_catalog_variant_id', (string) $variant->id)
            ->call('confirmGuestService', 1, 0)
            ->set('paymentMethod', 'cash');

        $pricing = $component->get('pricingPreview');
        $this->assertCount(2, $pricing['service_lines']);
        $this->assertSame(3, $pricing['service_lines'][0]['free_units']);
        $this->assertSame(0, $pricing['service_lines'][1]['free_units']);
        $this->assertSame(600_000, $pricing['services_discount_amount']);

        $component->call('submit')->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $services = $booking->services()->orderBy('sort_order')->get();
        $this->assertCount(2, $services);
        $this->assertSame(0, $services[0]->guest_sort_order);
        $this->assertSame(1, $services[1]->guest_sort_order);
        $this->assertSame(3, $services[0]->free_units);
        $this->assertSame(0, $services[1]->free_units);
        $this->assertFalse($services[0]->excluded_from_veteran_quota);
        $this->assertFalse($services[1]->excluded_from_veteran_quota);
    }

    public function test_excluded_from_veteran_quota_service_charges_full_price_with_host_discount(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);
        [$pool, $variant] = $this->createPoolVariant(200_000);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز')
            ->set('guestContactMobile', '09144401236')
            ->call('nextStep')
            ->set('guestDetails.0.services.0.service_catalog_id', (string) $pool->id)
            ->set('guestDetails.0.services.0.service_catalog_variant_id', (string) $variant->id)
            ->set('guestDetails.0.services.0.excluded_from_veteran_quota', true)
            ->set('guestDetails.0.services.0.manual_discount_percentage', '25')
            ->set('guestDetails.0.services.0.manual_discount_reason', 'پرداخت مستقیم')
            ->call('confirmGuestService', 0, 0)
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $service = $booking->services()->first();
        $this->assertTrue($service->excluded_from_veteran_quota);
        $this->assertSame(0, $service->free_units);
        $this->assertSame(25, $service->manual_discount_percentage);
        $this->assertSame('پرداخت مستقیم', $service->manual_discount_reason);
        $this->assertSame(50_000, $service->discount_amount);
        $this->assertSame(150_000, $service->total);

        $pricing = app(BookingPricingService::class)->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'        => 1,
            'veteran_type'  => 'veteran_70_spouses',
            'accommodation' => $this->accommodation,
            'room_type'     => $this->roomType,
            'room_rate'     => $this->roomRate,
            'services'      => [[
                'service_catalog_id'         => $pool->id,
                'service_catalog_variant_id' => $variant->id,
                'guest_sort_order'           => 0,
                'name'                       => 'استخر — تست',
                'unit_price'                 => 200_000,
                'quantity'                   => 1,
                'excluded_from_veteran_quota' => true,
                'manual_discount_percentage' => 25,
            ]],
        ]);

        $this->assertSame(0, $pricing['service_lines'][0]['free_units']);
        $this->assertSame(50_000, $pricing['service_lines'][0]['discount_amount']);
        $this->assertSame(150_000, $pricing['service_lines'][0]['line_total']);
    }

    public function test_excluded_service_does_not_consume_weekly_free_quota(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);
        [$pool, $variant] = $this->createPoolVariant(100_000);
        $policy = $this->veteranPolicyFor($this->accommodation);

        $priorBooking = Booking::create([
            'user_id'          => User::create([
                'name'         => 'قبلی',
                'mobile'       => '09120000088',
                'national_id'  => '4440123456',
                'veteran_type' => 'veteran_70_spouses',
            ])->id,
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => $checkIn,
            'check_out'        => Carbon::parse($checkIn)->addDay()->format('Y-m-d'),
            'guests'           => 1,
            'nights'           => 1,
            'base_price'       => 1_000_000,
            'total_price'      => 1_000_000,
            'status'           => 'confirmed',
            'booking_source'   => 'manual',
            'veteran_type_applied' => 'veteran_70_spouses',
            'tracking_code'    => 'PRIORQUOTA1',
        ]);

        BookingService::create([
            'booking_id'                 => $priorBooking->id,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                         => 'استخر',
            'unit_price'                   => 100_000,
            'quantity'                     => 2,
            'free_units'                   => 2,
            'discount_percentage'          => 0,
            'discount_amount'              => 200_000,
            'total'                        => 0,
            'excluded_from_veteran_quota'  => true,
        ]);

        $used = $policy->usedFreeSessionsInWeek(
            'veteran_70_spouses',
            '4440123456',
            $priorBooking->user_id,
            $pool->id,
            $checkIn,
        );

        $this->assertSame(0, $used);
    }

    public function test_step_validations_block_flow_until_requirements_met(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->set('checkIn', $checkIn)
            ->set('checkOut', $checkOut)
            ->call('nextStep')
            ->assertHasErrors(['roomLines'])
            ->assertSet('step', 1)
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->call('nextStep')
            ->assertHasErrors(['bookerNationalId'])
            ->assertSet('step', 2)
            ->set('bookerNationalId', '4440111222')
            ->call('verifyBooker')
            ->call('nextStep')
            ->assertHasErrors(['guestContactName', 'guestContactMobile'])
            ->assertSet('step', 2);
    }

    public function test_manual_booking_validation_messages_are_persian(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('nextStep')
            ->assertHasErrors(['checkIn', 'checkOut'])
            ->assertSee('تاریخ ورود الزامی است')
            ->assertSee('تاریخ خروج الزامی است')
            ->assertDispatched('toast', function ($name, $params) {
                $message = is_array($params) ? ($params['message'] ?? '') : '';

                return str_contains($message, 'تاریخ ورود الزامی است')
                    && str_contains($message, 'تاریخ خروج الزامی است');
            })
            ->assertDontSee('The check in field is required')
            ->assertDontSee('The check out field is required');
    }

    public function test_manual_discount_percentage_range_validation_messages_are_persian(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->set('bookerNationalId', '9999888777')
            ->call('verifyBooker')
            ->set('guestContactName', 'کاربر عادی')
            ->set('guestContactMobile', '09199998887')
            ->call('nextStep')
            ->set('guestDetails.0.manual_discount_percentage', '150')
            ->set('paymentMethod', 'cash')
            ->call('nextStep')
            ->assertHasErrors(['guestDetails.0.manual_discount_percentage'])
            ->assertSee('درصد تخفیف نباید بیشتر از ۱۰۰ باشد')
            ->assertDispatched('toast', fn ($name, $params) => str_contains(
                is_array($params) ? ($params['message'] ?? '') : '',
                'درصد تخفیف نباید بیشتر از ۱۰۰ باشد'
            ))
            ->assertDontSee('must not be greater than 100')
            ->set('guestDetails.0.manual_discount_percentage', '-5')
            ->call('nextStep')
            ->assertHasErrors(['guestDetails.0.manual_discount_percentage'])
            ->assertSee('درصد تخفیف نباید کمتر از ۰ باشد')
            ->assertDontSee('must be at least 0');
    }

    public function test_existing_guest_booker_skips_contact_validation(): void
    {
        $guest = User::create([
            'name'         => 'مهمان موجود',
            'mobile'       => '09120000005',
            'national_id'  => '4440333444',
            'veteran_type' => 'veteran_70_spouses',
        ]);
        $guest->assignRole('guest');

        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->set('bookerNationalId', '4440333444')
            ->call('verifyBooker')
            ->assertSet('bookerIsExistingUser', true)
            ->assertSet('userId', $guest->id)
            ->call('nextStep')
            ->assertSet('step', 3)
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $this->assertSame($guest->id, $booking->user_id);
    }

    public function test_manual_discount_requires_reason_when_percentage_set(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->set('bookerNationalId', '9999888777')
            ->call('verifyBooker')
            ->set('guestContactName', 'کاربر عادی')
            ->set('guestContactMobile', '09199998887')
            ->call('nextStep')
            ->set('guestDetails.1.manual_discount_percentage', '15')
            ->set('paymentMethod', 'cash')
            ->call('nextStep')
            ->assertHasErrors(['guestDetails.1.manual_discount_reason'])
            ->assertSet('step', 3)
            ->call('submit')
            ->assertHasErrors(['guestDetails.1.manual_discount_reason'])
            ->assertSet('step', 3);
    }

    public function test_apply_main_manual_discount_to_all_guests_at_payment_step(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 3, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->set('bookerNationalId', '9999888777')
            ->call('verifyBooker')
            ->set('guestContactName', 'کاربر عادی')
            ->set('guestContactMobile', '09199998887')
            ->call('nextStep')
            ->set('guestDetails.0.manual_discount_percentage', '20')
            ->set('guestDetails.0.manual_discount_reason', 'همکاری ویژه')
            ->call('applyMainManualDiscountToAllGuests')
            ->assertSet('guestDetails.1.manual_discount_percentage', '20')
            ->assertSet('guestDetails.1.manual_discount_reason', 'همکاری ویژه')
            ->assertSet('guestDetails.2.manual_discount_percentage', '20')
            ->assertSet('guestDetails.2.manual_discount_reason', 'همکاری ویژه')
            ->set('paymentMethod', 'cash')
            ->call('nextStep')
            ->assertSet('step', 4)
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        foreach ($booking->guestDetails as $guest) {
            $this->assertSame(20, $guest->manual_discount_percentage);
            $this->assertSame('همکاری ویژه', $guest->manual_discount_reason);
        }
    }

    public function test_pending_guest_service_blocks_next_step_until_confirmed_or_removed(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);
        [$pool, $variant] = $this->createPoolVariant(200_000);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز')
            ->set('guestContactMobile', '09144401239')
            ->call('nextStep')
            ->call('addGuestService', 0)
            ->set('guestDetails.0.services.0.service_catalog_id', (string) $pool->id)
            ->set('guestDetails.0.services.0.service_catalog_variant_id', (string) $variant->id)
            ->set('paymentMethod', 'cash')
            ->call('nextStep')
            ->assertHasErrors(['guestDetails.0.services.0.service_catalog_id'])
            ->assertSet('step', 3)
            ->call('confirmGuestService', 0, 0)
            ->call('nextStep')
            ->assertSet('step', 4);
    }

    public function test_service_manual_discount_requires_reason_when_excluded_from_quota(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);
        [$pool, $variant] = $this->createPoolVariant(200_000);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز')
            ->set('guestContactMobile', '09144401237')
            ->call('nextStep')
            ->set('guestDetails.0.services.0.service_catalog_id', (string) $pool->id)
            ->set('guestDetails.0.services.0.service_catalog_variant_id', (string) $variant->id)
            ->set('guestDetails.0.services.0.excluded_from_veteran_quota', true)
            ->set('guestDetails.0.services.0.manual_discount_percentage', '10')
            ->call('confirmGuestService', 0, 0)
            ->assertHasErrors(['guestDetails.0.services.0.manual_discount_reason']);
    }

    public function test_veteran_eligible_guest_manual_discount_ignored_on_persist(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id)
            ->call('nextStep')
            ->set('bookerNationalId', '4440777888')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز')
            ->set('guestContactMobile', '09144407778')
            ->call('nextStep')
            ->set('guestDetails.1.manual_discount_percentage', '10')
            ->set('guestDetails.1.manual_discount_reason', 'نباید ذخیره شود')
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $guestTwo = $booking->guestDetails->firstWhere('sort_order', 1);
        $this->assertNotNull($guestTwo);
        $this->assertNull($guestTwo->manual_discount_percentage);
        $this->assertNull($guestTwo->manual_discount_reason);
        $this->assertFalse($guestTwo->excluded_from_veteran_discount);
    }

    public function test_submit_preview_exposes_editable_price_confirmation(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 4, $this->roomType->id, $this->roomRate->id, 0, false, 0, 4)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز تست')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep')
            ->set('guestDetails.2.excluded_from_veteran_discount', true)
            ->set('guestDetails.3.excluded_from_veteran_discount', true)
            ->set('paymentMethod', 'card_terminal')
            ->call('nextStep')
            ->assertSet('step', 4);

        $component->call('previewBookingPriceChange', 'submitManualBooking', [])
            ->assertReturned(fn (array $result) => $result['error'] === false
                && $result['affects_price'] === true
                && $result['auto_delta'] === 0
                && ($result['price_input_mode'] ?? '') === 'absolute'
                && $result['current_total'] === 5_200_000
                && $result['action_label'] === 'ثبت رزرو و صدور فیش');
    }

    public function test_execute_confirmed_submit_applies_custom_price_delta(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 4, $this->roomType->id, $this->roomRate->id, 0, false, 0, 4)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123999')
            ->call('verifyBooker')
            ->set('guestContactName', 'جانباز تست قیمت')
            ->set('guestContactMobile', '09144401299')
            ->call('nextStep')
            ->set('guestDetails.2.excluded_from_veteran_discount', true)
            ->set('guestDetails.3.excluded_from_veteran_discount', true)
            ->set('paymentMethod', 'card_terminal')
            ->call('nextStep')
            ->call('executeConfirmedPriceChange', 'submitManualBooking', 300_000, [])
            ->assertSet('step', 5);

        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertNotNull($booking);
        $this->assertSame(5_500_000, (int) $booking->total_price);
    }

    /** @return array{0:ServiceCatalog,1:ServiceCatalogVariant} */
    private function createPoolVariant(int $price): array
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'pool_test',
            'name'               => 'استخر تست',
            'price'              => $price,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        return [$pool, $variant];
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');

        return [$checkIn, $checkOut];
    }

    public function test_veteran_host_does_not_apply_discount_before_guest_verification(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $host = User::create([
            'name'                => 'میزبان ایثارگر',
            'mobile'              => '09120001111',
            'national_id'         => '1111111111',
            'veteran_type'        => 'veteran_70_spouses',
            'discount_percentage' => 70,
        ]);
        $host->assignRole('host');
        $this->accommodation->hosts()->attach($host->id);

        [$checkIn, $checkOut] = $this->futureStay(2);

        $component = Livewire::actingAs($host)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'host',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->assertSet('bookerVerified', false)
            ->assertSet('discountPct', 0);

        $pricingBefore = $component->get('pricingPreview');
        $this->assertNotEmpty($pricingBefore);
        $this->assertSame(0, $pricingBefore['veteran_accommodation_discount_amount'] ?? 0);
        $this->assertSame(4_000_000, $pricingBefore['total_price']);

        $component
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->assertSet('bookerVerified', true)
            ->assertSet('discountPct', 70);

        $pricingAfter = $component->get('pricingPreview');
        $this->assertGreaterThan(0, $pricingAfter['veteran_accommodation_discount_amount'] ?? 0);
        $this->assertLessThan($pricingBefore['total_price'], $pricingAfter['total_price']);
    }

    public function test_multi_room_booking_assigns_guests_to_physical_rooms(): void
    {
        $room101 = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۱',
            'sort_order'   => 1,
            'is_active'    => true,
        ]);
        $room102 = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۲',
            'sort_order'   => 2,
            'is_active'    => true,
        ]);

        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2, $room101->id, '۱۰۱')
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2, $room102->id, '۱۰۲')
            ->assertSet('totalGuests', 4)
            ->assertCount('roomLines', 2)
            ->call('nextStep')
            ->set('bookerNationalId', '4440666777')
            ->call('verifyBooker')
            ->set('guestContactName', 'خانواده چهار نفره')
            ->set('guestContactMobile', '09144406667')
            ->call('nextStep')
            ->assertSet('step', 3)
            ->assertSet('guestDetails.0.room_name', '۱۰۱')
            ->assertSet('guestDetails.1.room_name', '۱۰۱')
            ->assertSet('guestDetails.2.room_name', '۱۰۲')
            ->assertSet('guestDetails.3.room_name', '۱۰۲')
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $this->assertSame(4, $booking->guestDetails()->count());

        $room101Guests = $booking->guestDetails()->whereHas('bookingRoom', fn ($q) => $q->where('room_id', $room101->id))->count();
        $room102Guests = $booking->guestDetails()->whereHas('bookingRoom', fn ($q) => $q->where('room_id', $room102->id))->count();
        $this->assertSame(2, $room101Guests);
        $this->assertSame(2, $room102Guests);
    }
}
