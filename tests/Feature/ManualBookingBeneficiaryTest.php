<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\BookingBeneficiaryCost;
use App\Models\ProgramBeneficiary;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingBeneficiaryTest extends TestCase
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
            'is_active'       => true,
        ]);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000099',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_manual_booking_has_five_steps_and_beneficiary_step(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->assertSet('step', 2)
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'مهمان تست')
            ->set('guestContactMobile', '09144401234')
            ->call('nextStep')
            ->assertSet('step', 3)
            ->call('nextStep')
            ->assertSet('step', 4)
            ->assertSee('ذینفع');
    }

    public function test_manual_booking_persists_beneficiary_costs_and_links_user(): void
    {
        $beneficiary = ProgramBeneficiary::create([
            'name'                    => 'ذی‌نفع رزرو',
            'beneficiary_code'        => 'MB-001',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121112233',
        ]);

        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '4440999888')
            ->call('verifyBooker')
            ->set('guestContactName', 'مهمان تست')
            ->set('guestContactMobile', '09144409998')
            ->call('nextStep')
            ->set('paymentMethod', 'cash')
            ->call('nextStep')
            ->assertSet('step', 4)
            ->set('beneficiaryRows', [
                [
                    'program_beneficiary_id' => (string) $beneficiary->id,
                    'debt_amount'            => '500000',
                    'description'            => 'بدهی تست',
                    'documents'              => [],
                ],
            ])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $booking = Booking::latest('id')->first();
        $this->assertNotNull($booking);

        $cost = BookingBeneficiaryCost::where('booking_id', $booking->id)->first();
        $this->assertNotNull($cost);
        $this->assertSame(500000, (int) $cost->debt_amount);
        $this->assertSame('بدهی تست', $cost->description);

        $beneficiary->refresh();
        $this->assertNotNull($beneficiary->user_id);
        $this->assertSame($beneficiary->user_id, $cost->user_id);

        $user = User::find($beneficiary->user_id);
        $this->assertTrue($user->hasRole('guest'));
        $this->assertSame('09121112233', $user->mobile);
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = now()->addDays(10 + $nights)->format('Y-m-d');

        return [$checkIn, $checkOut];
    }
}
