<?php

namespace Tests\Feature\Api;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\CancellationReason;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestApiFullTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;
    private Accommodation $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private int $provinceId;
    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->guest = User::create([
            'name'                => 'رضا صادقی',
            'mobile'              => '09120000001',
            'mobile_verified_at'  => now(),
            'discount_percentage' => 40,
        ]);
        $this->guest->assignRole('guest');

        $this->provinceId = DB::table('provinces')->insertGetId([
            'name' => 'استان تست', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->cityId = DB::table('cities')->insertGetId([
            'province_id' => $this->provinceId,
            'name'        => 'شهر تست',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->accommodation = $this->createTestAccommodation([
            'city_id'  => $this->cityId,
            'capacity' => 10,
        ]);

        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'room_count'       => 5,
            'is_active'        => true,
        ]);

        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ پایه',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
    }

    private function auth(): static
    {
        Sanctum::actingAs($this->guest, ['guest-api']);

        return $this;
    }

    private function futureCheckIn(int $days = 10): string
    {
        return Carbon::today()->addDays($days)->toDateString();
    }

    private function futureCheckOut(int $days = 13): string
    {
        return Carbon::today()->addDays($days)->toDateString();
    }

    public function test_send_otp(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['mobile' => '09120000001'])
            ->assertOk()
            ->assertJsonPath('mobile', '09120000001');
    }

    public function test_send_otp_rejects_invalid_mobile(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['mobile' => '1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mobile']);
    }

    public function test_verify_otp_returns_token(): void
    {
        $this->postJson('/api/v1/auth/otp/verify', [
            'mobile' => '09120000001',
            'otp'    => '123456',
        ])->assertOk()->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_provinces_list(): void
    {
        $this->getJson('/api/v1/provinces')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_cities_by_province(): void
    {
        $this->getJson("/api/v1/provinces/{$this->provinceId}/cities")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'شهر تست');
    }

    public function test_locations_returns_provinces_with_nested_cities(): void
    {
        $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'cities' => [
                            ['id', 'name'],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.0.id', $this->provinceId)
            ->assertJsonPath('data.0.name', 'استان تست')
            ->assertJsonPath('data.0.cities.0.name', 'شهر تست');
    }

    public function test_accommodation_types_list(): void
    {
        $this->seed(\Database\Seeders\AccommodationTypeSeeder::class);

        $this->getJson('/api/v1/accommodation-types')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['key', 'label', 'is_system'],
                ],
            ])
            ->assertJsonFragment(['key' => 'hotel', 'label' => 'هتل', 'is_system' => true])
            ->assertJsonFragment(['key' => 'traditional', 'label' => 'اقامتگاه سنتی', 'is_system' => true]);
    }

    public function test_accommodations_index_filters_by_type(): void
    {
        $this->seed(\Database\Seeders\AccommodationTypeSeeder::class);

        $this->accommodation->update(['type' => 'hotel']);
        $this->createTestAccommodation(['name' => 'ویلای تست', 'type' => 'villa']);

        $this->getJson('/api/v1/accommodations?type=hotel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'hotel');

        $this->getJson('/api/v1/accommodations?type=invalid-type')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_accommodations_index(): void
    {
        $this->getJson('/api/v1/accommodations')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_accommodation_show(): void
    {
        $this->getJson("/api/v1/accommodations/{$this->accommodation->id}")
            ->assertOk()
            ->assertJsonStructure(['data', 'room_types', 'reviews']);
    }

    public function test_inactive_accommodation_show_returns_404(): void
    {
        $this->accommodation->update(['is_active' => false]);

        $this->getJson("/api/v1/accommodations/{$this->accommodation->id}")
            ->assertNotFound();
    }

    public function test_rooms_availability(): void
    {
        $this->getJson("/api/v1/accommodations/{$this->accommodation->id}/rooms-availability?" . http_build_query([
            'check_in'  => $this->futureCheckIn(),
            'check_out' => $this->futureCheckOut(),
        ]))->assertOk();
    }

    public function test_room_type_availability(): void
    {
        $this->getJson("/api/v1/room-types/{$this->roomType->id}/availability?months=" . Carbon::today()->format('Y-m'))
            ->assertOk()
            ->assertJsonStructure(['dates']);
    }

    public function test_physical_rooms(): void
    {
        $this->getJson("/api/v1/room-types/{$this->roomType->id}/physical-rooms?" . http_build_query([
            'check_in'  => $this->futureCheckIn(),
            'check_out' => $this->futureCheckOut(),
        ]))->assertOk()
            ->assertJsonStructure(['rooms', 'room_type']);
    }

    public function test_profile_show_and_update(): void
    {
        $this->auth()
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('user.mobile', '09120000001');

        $this->putJson('/api/v1/profile', ['name' => 'رضا جدید'])
            ->assertOk()
            ->assertJsonPath('user.name', 'رضا جدید');
    }

    public function test_favorites_flow(): void
    {
        $this->auth()
            ->postJson("/api/v1/favorites/{$this->accommodation->id}/toggle")
            ->assertOk()
            ->assertJsonPath('favorited', true);

        $this->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_create_booking_with_children_under_6(): void
    {
        $response = $this->auth()->postJson(
            "/api/v1/accommodations/{$this->accommodation->id}/bookings",
            [
                'check_in'         => $this->futureCheckIn(),
                'check_out'        => $this->futureCheckOut(),
                'guests'           => 3,
                'children_under_6' => 1,
                'room_type_id'     => $this->roomType->id,
                'room_rate_id'     => $this->roomRate->id,
            ]
        );

        $response->assertCreated()
            ->assertJsonPath('data.children_under_6', 1)
            ->assertJsonPath('data.guests', 3);

        $this->assertDatabaseHas('bookings', [
            'user_id'          => $this->guest->id,
            'children_under_6' => 1,
            'guests'           => 3,
        ]);
    }

    public function test_create_booking_rejects_children_equal_to_guests(): void
    {
        $this->auth()->postJson(
            "/api/v1/accommodations/{$this->accommodation->id}/bookings",
            [
                'check_in'         => $this->futureCheckIn(),
                'check_out'        => $this->futureCheckOut(),
                'guests'           => 2,
                'children_under_6' => 2,
            ]
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['guests']);
    }

    public function test_bookings_index_and_show(): void
    {
        $booking = Booking::create([
            'user_id'          => $this->guest->id,
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => $this->futureCheckIn(),
            'check_out'        => $this->futureCheckOut(),
            'nights'           => 3,
            'guests'           => 2,
            'children_under_6' => 0,
            'base_price'       => 3_000_000,
            'total_price'      => 3_000_000,
            'status'           => 'confirmed',
            'booking_source'   => 'online',
            'tracking_code'    => 'API1234567',
        ]);

        $this->auth()
            ->getJson('/api/v1/bookings')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->getJson("/api/v1/bookings/{$booking->id}")
            ->assertOk()
            ->assertJsonPath('data.tracking_code', 'API1234567');
    }

    public function test_booking_pdf(): void
    {
        $booking = Booking::create([
            'user_id'          => $this->guest->id,
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => $this->futureCheckIn(),
            'check_out'        => $this->futureCheckOut(),
            'nights'           => 3,
            'guests'           => 2,
            'base_price'       => 3_000_000,
            'total_price'      => 3_000_000,
            'status'           => 'confirmed',
            'booking_source'   => 'online',
            'tracking_code'    => 'PDF1234567',
        ]);

        $this->auth()
            ->get("/api/v1/bookings/{$booking->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_cancellation_reasons_and_preview(): void
    {
        $booking = Booking::create([
            'user_id'          => $this->guest->id,
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => $this->futureCheckIn(20),
            'check_out'        => $this->futureCheckOut(23),
            'nights'           => 3,
            'guests'           => 2,
            'base_price'       => 3_000_000,
            'total_price'      => 3_000_000,
            'status'           => 'confirmed',
            'booking_source'   => 'online',
            'tracking_code'    => 'CXL1234567',
        ]);

        $this->auth()
            ->getJson("/api/v1/bookings/{$booking->id}/cancellation-reasons")
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->getJson("/api/v1/bookings/{$booking->id}/cancellation-preview")
            ->assertOk()
            ->assertJsonStructure(['data' => ['days', 'percentage', 'amount']]);
    }

    public function test_submit_cancellation_request(): void
    {
        $booking = Booking::create([
            'user_id'          => $this->guest->id,
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => $this->futureCheckIn(20),
            'check_out'        => $this->futureCheckOut(23),
            'nights'           => 3,
            'guests'           => 2,
            'base_price'       => 3_000_000,
            'total_price'      => 3_000_000,
            'status'           => 'confirmed',
            'booking_source'   => 'online',
            'tracking_code'    => 'CXL2234567',
        ]);

        $reason = CancellationReason::query()
            ->forAccommodation($this->accommodation->id)
            ->active()
            ->where('is_custom', false)
            ->first();

        $this->assertNotNull($reason);

        $this->auth()->postJson("/api/v1/bookings/{$booking->id}/cancellation-requests", [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6037991234567890',
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'status', 'refund_amount']]);
    }

    public function test_review_requires_completed_stay(): void
    {
        $this->auth()->postJson("/api/v1/accommodations/{$this->accommodation->id}/reviews", [
            'rating'  => 5,
            'comment' => 'عالی',
        ])->assertUnprocessable();
    }

    public function test_review_after_completed_stay(): void
    {
        Booking::create([
            'user_id'          => $this->guest->id,
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => Carbon::today()->subDays(10)->toDateString(),
            'check_out'        => Carbon::today()->subDays(7)->toDateString(),
            'nights'           => 3,
            'guests'           => 2,
            'base_price'       => 3_000_000,
            'total_price'      => 3_000_000,
            'status'           => 'confirmed',
            'booking_source'   => 'online',
            'tracking_code'    => 'REV1234567',
        ]);

        $this->auth()->postJson("/api/v1/accommodations/{$this->accommodation->id}/reviews", [
            'rating'  => 5,
            'comment' => 'اقامت خوب بود',
        ])->assertCreated();
    }
}
