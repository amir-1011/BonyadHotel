<?php

namespace Tests\Feature\Api;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestApiTest extends TestCase
{
    use RefreshDatabase;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->guest = User::create([
            'name'                => 'رضا صادقی',
            'mobile'              => '09120000001',
            'mobile_verified_at'  => now(),
            'discount_percentage' => 40,
        ]);
        $this->guest->assignRole('guest');
    }

    public function test_otp_verify_returns_bearer_token(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'mobile'      => '09120000001',
            'otp'         => '123456',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'expires_at',
                'user' => ['id', 'name', 'mobile'],
            ])
            ->assertJsonPath('token_type', 'Bearer');
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        Sanctum::actingAs($this->guest, ['guest-api']);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.mobile', '09120000001');
    }

    public function test_logout_revokes_current_token(): void
    {
        $token = $this->guest->createToken('test', ['guest-api']);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        \Illuminate\Support\Facades\Auth::forgetGuards();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_provinces_are_public(): void
    {
        $this->getJson('/api/v1/provinces')->assertOk();
    }

    public function test_favorites_toggle_requires_auth(): void
    {
        $accommodation = $this->createAccommodation();

        $this->postJson("/api/v1/favorites/{$accommodation->id}/toggle")
            ->assertUnauthorized();
    }

    public function test_favorites_toggle_works_with_bearer_token(): void
    {
        Sanctum::actingAs($this->guest, ['guest-api']);
        $accommodation = $this->createAccommodation();

        $this->postJson("/api/v1/favorites/{$accommodation->id}/toggle")
            ->assertOk()
            ->assertJsonPath('favorited', true);

        $this->postJson("/api/v1/favorites/{$accommodation->id}/toggle")
            ->assertOk()
            ->assertJsonPath('favorited', false);
    }

    public function test_user_cannot_view_other_users_booking(): void
    {
        Sanctum::actingAs($this->guest, ['guest-api']);
        $accommodation = $this->createAccommodation();

        $other = User::create(['name' => 'دیگری', 'mobile' => '09120000099']);
        $booking = Booking::create([
            'user_id'          => $other->id,
            'accommodation_id' => $accommodation->id,
            'check_in'         => now()->addDays(5)->toDateString(),
            'check_out'        => now()->addDays(8)->toDateString(),
            'nights'           => 3,
            'guests'           => 2,
            'base_price'       => 1000000,
            'total_price'      => 1000000,
            'status'           => 'confirmed',
            'booking_source'   => 'online',
            'tracking_code'    => 'TEST123456',
        ]);

        $this->getJson("/api/v1/bookings/{$booking->id}")->assertForbidden();
    }

    private function createAccommodation(): Accommodation
    {
        return $this->createTestAccommodation();
    }
}
