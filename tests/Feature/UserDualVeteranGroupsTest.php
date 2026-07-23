<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserEdit;
use App\Livewire\ManualBookingForm;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Support\HostUserFilter;
use App\Support\VeteranGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserDualVeteranGroupsTest extends TestCase
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
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

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
            'mobile' => '09000000111',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_user_model_shows_both_veteran_group_labels(): void
    {
        $user = User::create([
            'name'                   => 'مهمان دو گروهی',
            'mobile'                 => '09120000001',
            'national_id'            => '4440111222',
            'veteran_type'           => 'veteran_70_spouses',
            'secondary_veteran_type' => 'martyr_children',
            'discount_percentage'    => 70,
        ]);

        $label = $user->veteranLabel();
        $this->assertStringContainsString('جانبازان ۷۰ درصد', $label);
        $this->assertStringContainsString('فرزندان شهدا', $label);
        $this->assertStringContainsString('+', $label);
        $this->assertSame(
            ['veteran_70_spouses', 'martyr_children'],
            $user->normalizedVeteranTypes(),
        );
    }

    public function test_admin_user_edit_saves_two_veteran_groups(): void
    {
        $user = User::create([
            'name'   => 'کاربر تست',
            'mobile' => '09120000002',
        ]);
        $user->assignRole('guest');

        Livewire::actingAs($this->adminUser)
            ->test(UserEdit::class, ['user' => $user])
            ->set('selectedVeteranTypes', ['veteran_70_spouses', 'martyr_children'])
            ->call('update')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('veteran_70_spouses', $user->veteran_type);
        $this->assertSame('martyr_children', $user->secondary_veteran_type);
        $this->assertSame(70, $user->discount_percentage);
        $this->assertStringContainsString('+', $user->veteranLabel());
    }

    public function test_manual_booking_persists_dual_groups_on_new_user(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '4440333444')
            ->call('verifyBooker')
            ->set('selectedVeteranTypes', ['veteran_70_spouses', 'martyr_children'])
            ->set('guestContactName', 'مهمان جدید')
            ->set('guestContactMobile', '09144403344')
            ->call('nextStep')
            ->set('paymentMethod', 'cash')
            ->call('submit')
            ->assertSet('step', 5);

        $guestUser = User::where('national_id', '4440333444')->first();
        $this->assertNotNull($guestUser);
        $this->assertSame('veteran_70_spouses', $guestUser->veteran_type);
        $this->assertSame('martyr_children', $guestUser->secondary_veteran_type);
        $this->assertSame(70, $guestUser->discount_percentage);
    }

    public function test_manual_booking_loads_existing_user_dual_groups(): void
    {
        $existing = User::create([
            'name'                   => 'مهمان موجود',
            'mobile'                 => '09120000003',
            'national_id'            => '4440555666',
            'veteran_type'           => 'veteran_70_spouses',
            'secondary_veteran_type' => 'martyr_children',
            'discount_percentage'    => 70,
        ]);
        $existing->assignRole('guest');

        [$checkIn, $checkOut] = $this->futureStay(1);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '4440555666')
            ->call('verifyBooker')
            ->assertSet('veteranType', 'veteran_70_spouses')
            ->assertSet('secondaryVeteranType', 'martyr_children')
            ->assertSet('selectedVeteranTypes', ['veteran_70_spouses', 'martyr_children']);
    }

    public function test_host_user_filter_matches_secondary_veteran_group(): void
    {
        $user = User::create([
            'name'                   => 'فیلتر تست',
            'mobile'                 => '09120000004',
            'national_id'            => '4440777888',
            'veteran_type'           => 'veteran_50_69_dependents',
            'secondary_veteran_type' => 'martyr_children',
            'discount_percentage'    => 50,
        ]);
        $user->assignRole('guest');

        $bookingUser = User::create([
            'name'   => 'میزبان',
            'mobile' => '09120000005',
        ]);
        $bookingUser->assignRole('host');
        $this->accommodation->grantHostAccess($bookingUser);

        $user->bookings()->create([
            'accommodation_id' => $this->accommodation->id,
            'room_type_id'     => $this->roomType->id,
            'room_rate_id'     => $this->roomRate->id,
            'check_in'         => now()->addDays(3),
            'check_out'        => now()->addDays(4),
            'guests'           => 1,
            'nights'           => 1,
            'base_price'       => 1_000_000,
            'total_price'      => 1_000_000,
            'status'           => 'confirmed',
            'tracking_code'    => 'TESTCODE01',
        ]);

        $query = User::query();
        HostUserFilter::make(['veteran_type' => 'martyr_children'], [$this->accommodation->id])
            ->apply($query);

        $this->assertTrue($query->where('users.id', $user->id)->exists());
    }

    public function test_accommodation_discount_for_types_matches_user_profile(): void
    {
        $types = ['veteran_70_spouses', 'martyr_children'];
        $expected = VeteranGroups::accommodationDiscountForTypes($types, $this->accommodation->id);

        $user = User::create([
            'name'                   => 'تخفیف تست',
            'mobile'                 => '09120000006',
            'veteran_type'           => 'veteran_70_spouses',
            'secondary_veteran_type' => 'martyr_children',
            'discount_percentage'    => $expected,
        ]);

        $this->assertSame($expected, $user->accommodationDiscountFor($this->accommodation->id));
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = now()->addDays(10 + $nights)->format('Y-m-d');

        return [$checkIn, $checkOut];
    }
}
