<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\Country;
use App\Models\ResidenceCity;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\LocationCatalogService;
use App\Services\ManualBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingForeignGuestTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private User $adminUser;
    private Country $country;
    private ResidenceCity $residenceCity;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق دو تخته',
            'capacity'         => 2,
            'room_count'       => 3,
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

        $this->country = Country::create(['name' => 'ترکیه']);
        $this->residenceCity = ResidenceCity::create([
            'country_id' => $this->country->id,
            'name'       => 'استانبول',
        ]);
    }

    public function test_foreign_guest_step_two_renders_location_fields(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('bookerIsForeignGuest', true)
            ->assertSee('مهمان خارجی')
            ->assertSee('شماره پاسپورت')
            ->assertSee('کشور اقامت')
            ->assertSee('شهر اقامت')
            ->assertSee('کشور در لیست نیست؟ افزودن');
    }

    public function test_can_add_country_and_residence_city_from_manual_booking_form(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerIsForeignGuest', true)
            ->call('toggleAddCountry')
            ->set('newCountryName', 'آلمان')
            ->call('addCountry')
            ->assertHasNoErrors()
            ->assertSet('showAddCountry', false);

        $germanyId = $component->get('foreignCountryId');
        $this->assertTrue(Country::where('name', 'آلمان')->exists());
        $this->assertSame($germanyId, Country::where('name', 'آلمان')->value('id'));

        $component
            ->call('toggleAddResidenceCity')
            ->set('newResidenceCityName', 'برلین')
            ->call('addResidenceCity')
            ->assertHasNoErrors()
            ->assertSet('showAddResidenceCity', false);

        $this->assertTrue(
            ResidenceCity::where('country_id', $germanyId)->where('name', 'برلین')->exists()
        );
    }

    public function test_foreign_guest_verify_rejects_staff_mobile(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerIsForeignGuest', true)
            ->set('bookerPassportNumber', 'AB1234567')
            ->set('foreignCountryId', $this->country->id)
            ->set('foreignResidenceCityId', $this->residenceCity->id)
            ->set('guestContactName', 'John Doe')
            ->set('guestContactMobile', $this->adminUser->mobile)
            ->call('verifyBooker')
            ->assertSet('bookerVerified', false)
            ->assertHasErrors(['guestContactMobile']);
    }

    public function test_foreign_guest_verify_rejects_city_from_other_country(): void
    {
        $otherCountry = Country::create(['name' => 'آلمان']);
        $otherCity = ResidenceCity::create([
            'country_id' => $otherCountry->id,
            'name'       => 'برلین',
        ]);

        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerIsForeignGuest', true)
            ->set('bookerPassportNumber', 'AB1234567')
            ->set('foreignCountryId', $this->country->id)
            ->set('foreignResidenceCityId', $otherCity->id)
            ->set('guestContactName', 'John Doe')
            ->set('guestContactMobile', '09120009988')
            ->call('verifyBooker')
            ->assertSet('bookerVerified', false)
            ->assertHasErrors(['foreignResidenceCityId']);
    }

    public function test_foreign_guest_full_flow_creates_user_and_guest_detail(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerIsForeignGuest', true)
            ->set('bookerPassportNumber', 'xy9876543')
            ->set('foreignCountryId', $this->country->id)
            ->set('foreignResidenceCityId', $this->residenceCity->id)
            ->set('guestContactName', 'John Smith')
            ->set('guestContactMobile', '09127778899')
            ->call('verifyBooker')
            ->assertSet('bookerVerified', true)
            ->assertSet('bookerPassportNumber', 'XY9876543')
            ->assertSet('veteranType', '')
            ->assertSet('discountPct', 0)
            ->call('nextStep')
            ->assertSet('step', 3)
            ->assertSee('پاسپورت')
            ->assertSee('استانبول')
            ->assertSee('ترکیه')
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertNull($booking->veteran_type_applied);
        $this->assertSame(0, $booking->discount_percentage);
        $this->assertSame(1_000_000, $booking->total_price);

        $booker = $booking->guestDetails()->where('sort_order', 0)->first();
        $this->assertTrue($booker->is_foreign_guest);
        $this->assertSame('XY9876543', $booker->passport_number);
        $this->assertNull($booker->national_id);
        $this->assertSame($this->country->id, $booker->country_id);
        $this->assertSame($this->residenceCity->id, $booker->residence_city_id);
        $this->assertSame('John Smith', $booker->full_name);

        $guestUser = User::where('passport_number', 'XY9876543')->first();
        $this->assertNotNull($guestUser);
        $this->assertTrue($guestUser->is_foreign_guest);
        $this->assertNull($guestUser->national_id);
        $this->assertNull($guestUser->veteran_type);
        $this->assertTrue($guestUser->hasRole('guest'));

        $booking->load(['guestDetails.country', 'guestDetails.residenceCity', 'user.country', 'user.residenceCity']);
        $this->assertSame('پاسپورت', $booking->bookerIdentityLabel());
        $this->assertSame('XY9876543', $booking->bookerIdentityNumber());
        $this->assertSame('استانبول، ترکیه', $booking->bookerResidenceLabel());
        $this->assertSame('XY9876543', $guestUser->identityNumber());
        $this->assertSame('استانبول، ترکیه', $guestUser->residenceLocationLabel());
    }

    public function test_existing_foreign_guest_is_reused_by_passport(): void
    {
        $existing = User::create([
            'name'               => 'Jane Doe',
            'mobile'             => '09126667788',
            'is_foreign_guest'   => true,
            'passport_number'    => 'PP1122334',
            'country_id'         => $this->country->id,
            'residence_city_id'  => $this->residenceCity->id,
        ]);
        $existing->assignRole('guest');

        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerIsForeignGuest', true)
            ->set('bookerPassportNumber', 'pp1122334')
            ->set('foreignCountryId', $this->country->id)
            ->set('foreignResidenceCityId', $this->residenceCity->id)
            ->set('guestContactName', 'Ignored Name')
            ->set('guestContactMobile', '09126667788')
            ->call('verifyBooker')
            ->assertSet('bookerVerified', true)
            ->assertSet('bookerIsExistingUser', true)
            ->assertSet('userId', $existing->id)
            ->assertSet('guestContactName', 'Jane Doe');
    }

    public function test_toggling_foreign_guest_clears_iranian_booker_state(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '1234567890')
            ->set('bookerIsForeignGuest', true)
            ->assertSet('bookerNationalId', '')
            ->assertSet('bookerVerified', false)
            ->assertSet('foreignCountryId', 0);
    }

    public function test_resolve_foreign_guest_user_service_method(): void
    {
        $user = $this->resolveGuestUser([
            'booker_is_foreign_guest'   => true,
            'booker_passport_number'    => 'ab9988776',
            'foreign_country_id'        => $this->country->id,
            'foreign_residence_city_id' => $this->residenceCity->id,
            'guest_contact_name'        => 'Foreign Guest',
            'guest_contact_mobile'      => '09125556677',
        ]);

        $this->assertSame('AB9988776', $user->passport_number);
        $this->assertTrue($user->is_foreign_guest);
        $this->assertSame($this->country->id, $user->country_id);
        $this->assertSame($this->residenceCity->id, $user->residence_city_id);
    }

    public function test_location_catalog_service_persists_country_and_city(): void
    {
        $service = app(LocationCatalogService::class);

        $country = $service->createCountry('فرانسه');
        $city = $service->createResidenceCity($country->id, 'پاریس');

        $this->assertDatabaseHas('countries', ['name' => 'فرانسه']);
        $this->assertDatabaseHas('residence_cities', [
            'country_id' => $country->id,
            'name'       => 'پاریس',
        ]);
        $this->assertSame($city->id, $service->resolveOrCreateResidenceCity('فرانسه', 'پاریس')['id']);
    }

    public function test_foreign_guest_cannot_advance_without_verification(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerIsForeignGuest', true)
            ->set('bookerPassportNumber', 'AB1234567')
            ->set('foreignCountryId', $this->country->id)
            ->set('foreignResidenceCityId', $this->residenceCity->id)
            ->set('guestContactName', 'John Doe')
            ->set('guestContactMobile', '09120009988')
            ->call('nextStep')
            ->assertSet('step', 2)
            ->assertHasErrors(['bookerPassportNumber']);
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');

        return [$checkIn, $checkOut];
    }

    /** @param array<string, mixed> $data */
    private function resolveGuestUser(array $data): User
    {
        $service = app(ManualBookingService::class);
        $method = new ReflectionMethod(ManualBookingService::class, 'resolveGuestUser');
        $method->setAccessible(true);

        return $method->invoke($service, $data, $this->accommodation->id);
    }
}
