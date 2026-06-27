<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingPricingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end Livewire manual booking flow: rooms → services → booker → payment → submit.
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
            ->call('nextStep')
            ->assertSet('step', 3)
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->assertSet('bookerVerified', true)
            ->assertSet('veteranType', 'veteran_70_spouses')
            ->set('guestContactName', 'جانباز تست')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep')
            ->assertSet('step', 4)
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
            ->call('nextStep')
            ->assertHasErrors(['bookerNationalId'])
            ->assertSet('step', 3)
            ->set('bookerNationalId', '4440111222')
            ->call('verifyBooker')
            ->call('nextStep')
            ->assertHasErrors(['guestContactName', 'guestContactMobile'])
            ->assertSet('step', 3);
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
            ->call('nextStep')
            ->set('bookerNationalId', '4440333444')
            ->call('verifyBooker')
            ->assertSet('bookerIsExistingUser', true)
            ->assertSet('userId', $guest->id)
            ->call('nextStep')
            ->assertSet('step', 4)
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
            ->call('nextStep')
            ->set('bookerNationalId', '9999888777')
            ->call('verifyBooker')
            ->set('guestContactName', 'کاربر عادی')
            ->set('guestContactMobile', '09199998887')
            ->call('nextStep')
            ->set('guestDetails.1.manual_discount_percentage', '15')
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertHasErrors(['guestDetails.1.manual_discount_reason'])
            ->assertSet('step', 4);
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

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');

        return [$checkIn, $checkOut];
    }
}
