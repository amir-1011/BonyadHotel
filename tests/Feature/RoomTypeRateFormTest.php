<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomTypeRateFormTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private RoomRate $rate;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name'   => 'ادمین تعرفه',
            'mobile' => '09000000999',
        ]);
        $this->admin->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان تست', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر تست', 'created_at' => now(), 'updated_at' => now()]);

        $this->accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه تعرفه',
            'price_per_night' => 500_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق استاندارد',
            'capacity'         => 2,
            'room_count'       => 1,
            'is_active'        => true,
        ]);

        $this->rate = RoomRate::create([
            'room_type_id'        => $this->roomType->id,
            'name'                => 'با صبحانه',
            'price_per_night'     => 1_500_000,
            'cancellation_policy' => 'free',
            'payment_type'        => 'pay_at_hotel',
            'is_active'           => true,
        ]);
    }

    public function test_edit_rate_validation_failure_opens_edit_panel_not_add_form(): void
    {
        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $updateUrl = route('admin.room-types.rates.update', [$this->accommodation, $this->roomType, $this->rate]);

        $response = $this->actingAs($this->admin)
            ->from($editUrl)
            ->put($updateUrl, $this->ratePayload('edit', [
                'rate_name' => '',
            ]));

        $response->assertRedirect($editUrl);
        $response->assertSessionHasErrors('rate_name');

        $page = $this->actingAs($this->admin)->get($editUrl);
        $page->assertOk();
        $html = $page->getContent();

        $this->assertStringContainsString('value="اتاق استاندارد"', $html);
        $this->assertStringContainsString('collapse show" id="editRate' . $this->rate->id, $html);
        $this->assertDoesNotMatchRegularExpression('/collapse show" id="addRateForm"/', $html);
        $this->assertStringContainsString('is-invalid', $html);
    }

    public function test_create_rate_validation_failure_opens_add_form_only(): void
    {
        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $storeUrl = route('admin.room-types.rates.store', [$this->accommodation, $this->roomType]);

        $response = $this->actingAs($this->admin)
            ->from($editUrl)
            ->post($storeUrl, $this->ratePayload('create', [
                'rate_name' => '',
            ]));

        $response->assertRedirect($editUrl);
        $response->assertSessionHasErrors('rate_name');

        $page = $this->actingAs($this->admin)->get($editUrl);
        $html = $page->getContent();

        $this->assertStringContainsString('collapse show" id="addRateForm"', $html);
        $this->assertDoesNotMatchRegularExpression('/collapse show" id="editRate' . $this->rate->id . '"/', $html);
    }

    public function test_edit_rate_validation_does_not_blank_other_rate_forms(): void
    {
        $secondRate = RoomRate::create([
            'room_type_id'        => $this->roomType->id,
            'name'                => 'بدون صبحانه',
            'price_per_night'     => 1_200_000,
            'cancellation_policy' => 'free',
            'payment_type'        => 'pay_at_hotel',
            'is_active'           => true,
        ]);

        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $updateUrl = route('admin.room-types.rates.update', [$this->accommodation, $this->roomType, $this->rate]);

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->put($updateUrl, $this->ratePayload('edit', [
                'rate_id'   => $this->rate->id,
                'rate_name' => '',
            ]))
            ->assertSessionHasErrors('rate_name');

        $html = $this->actingAs($this->admin)->get($editUrl)->getContent();

        $this->assertStringContainsString('collapse show" id="editRate' . $this->rate->id, $html);
        $this->assertEquals(1, substr_count($html, 'value="بدون صبحانه"'));
        $this->assertDoesNotMatchRegularExpression('/value="با صبحانه"/', $html);
    }

    public function test_can_deactivate_rate_on_update(): void
    {
        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $updateUrl = route('admin.room-types.rates.update', [$this->accommodation, $this->roomType, $this->rate]);

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->put($updateUrl, $this->ratePayload('edit', [
                'is_active' => '0',
            ]))
            ->assertRedirect($editUrl)
            ->assertSessionHas('status');

        $this->assertFalse($this->rate->fresh()->is_active);
    }

    public function test_omitted_is_active_field_deactivates_rate_like_unchecked_checkbox(): void
    {
        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $updateUrl = route('admin.room-types.rates.update', [$this->accommodation, $this->roomType, $this->rate]);

        $payload = $this->ratePayload('edit');
        unset($payload['is_active']);

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->put($updateUrl, $payload)
            ->assertRedirect($editUrl);

        $this->assertFalse($this->rate->fresh()->is_active);
    }

    public function test_can_reactivate_rate_on_update(): void
    {
        $this->rate->update(['is_active' => false]);

        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $updateUrl = route('admin.room-types.rates.update', [$this->accommodation, $this->roomType, $this->rate]);

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->put($updateUrl, $this->ratePayload('edit', [
                'is_active' => '1',
            ]))
            ->assertRedirect($editUrl);

        $this->assertTrue($this->rate->fresh()->is_active);
    }

    public function test_inactive_rate_edit_form_shows_off_state(): void
    {
        $this->rate->update(['is_active' => false]);

        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $html = $this->actingAs($this->admin)->get($editUrl)->getContent();

        $this->assertStringContainsString('rt-rate-active-toggle--off', $html);
        $this->assertStringContainsString('غیرفعال — مخفی از سایت', $html);
        $this->assertStringContainsString('bi-toggle-off', $html);
    }

    public function test_can_create_inactive_rate(): void
    {
        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $storeUrl = route('admin.room-types.rates.store', [$this->accommodation, $this->roomType]);

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->post($storeUrl, $this->ratePayload('create', [
                'rate_name'       => 'تعرفه غیرفعال',
                'price_per_night' => 900_000,
                'is_active'       => '0',
            ]))
            ->assertRedirect($editUrl);

        $this->assertDatabaseHas('room_rates', [
            'room_type_id' => $this->roomType->id,
            'name'         => 'تعرفه غیرفعال',
            'is_active'    => false,
        ]);
    }

    public function test_can_store_rate_with_formatted_price_per_night(): void
    {
        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $storeUrl = route('admin.room-types.rates.store', [$this->accommodation, $this->roomType]);

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->post($storeUrl, $this->ratePayload('create', [
                'rate_name'       => 'تعرفه با جداکننده',
                'price_per_night' => '2,350,000',
            ]))
            ->assertRedirect($editUrl)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('room_rates', [
            'room_type_id'    => $this->roomType->id,
            'name'            => 'تعرفه با جداکننده',
            'price_per_night' => 2_350_000,
        ]);
    }

    public function test_can_update_rate_with_formatted_price_per_night(): void
    {
        $editUrl = route('admin.room-types.edit', [$this->accommodation, $this->roomType]);
        $updateUrl = route('admin.room-types.rates.update', [$this->accommodation, $this->roomType, $this->rate]);

        $this->actingAs($this->admin)
            ->from($editUrl)
            ->put($updateUrl, $this->ratePayload('edit', [
                'price_per_night' => '1,750,000',
            ]))
            ->assertRedirect($editUrl)
            ->assertSessionHasNoErrors();

        $this->assertSame(1_750_000, $this->rate->fresh()->price_per_night);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ratePayload(string $scope, array $overrides = []): array
    {
        return array_merge([
            'rate_form_scope'     => $scope,
            'rate_id'             => $scope === 'edit' ? $this->rate->id : null,
            'rate_name'           => $this->rate->name,
            'price_per_night'     => $this->rate->price_per_night,
            'cancellation_policy' => 'free',
            'payment_type'        => 'pay_at_hotel',
            'is_active'           => '1',
        ], $overrides);
    }
}
