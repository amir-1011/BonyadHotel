<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Hall;
use App\Models\HallAmenity;
use App\Models\HallType;
use App\Models\Program;
use App\Models\User;
use App\Services\HallAmenityCatalogService;
use App\Services\HallTypeCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HallCrudTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private User $admin;
    private User $host;
    private HallType $hallType;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $this->admin = User::create(['name' => 'ادمین سالن', 'mobile' => '09120001111']);
        $this->admin->assignRole('super_admin');

        $this->host = User::create(['name' => 'میزبان سالن', 'mobile' => '09120001112']);
        $this->host->assignRole('host');

        $this->accommodation = $this->createTestAccommodation();
        $this->accommodation->hosts()->attach($this->host->id);

        $this->hallType = app(HallTypeCatalogService::class)->allOrdered()->first();
        $this->assertNotNull($this->hallType);
    }

    public function test_admin_can_create_and_list_a_hall(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.halls.store', $this->accommodation), [
                'name'         => 'سالن اصلی',
                'hall_type_id' => $this->hallType->id,
                'capacity'     => 120,
                'description'  => 'سالن همایش طبقه اول',
                'amenities'    => ['سیستم صوتی', 'ویدئو پروژکتور'],
                'is_active'    => 1,
                'sort_order'   => 0,
            ])
            ->assertRedirect(route('admin.halls.accommodation.index', $this->accommodation));

        $this->assertDatabaseHas('halls', [
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'سالن اصلی',
            'capacity'         => 120,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.halls.index'))
            ->assertOk()
            ->assertSee('سالن اصلی')
            ->assertSee('سالن همایش');
    }

    public function test_host_cannot_manage_unassigned_accommodation_halls(): void
    {
        $other = $this->createTestAccommodation(['name' => 'اقامتگاه دیگر']);

        $this->actingAs($this->host)
            ->get(route('host.halls.accommodation.index', $other))
            ->assertForbidden();
    }

    public function test_host_can_create_hall_for_managed_accommodation(): void
    {
        $this->actingAs($this->host)
            ->post(route('host.halls.store', $this->accommodation), [
                'name'         => 'سالن سینما',
                'hall_type_id' => $this->hallType->id,
                'capacity'     => 80,
                'is_active'    => 1,
            ])
            ->assertRedirect(route('host.halls.accommodation.index', $this->accommodation));

        $this->assertDatabaseHas('halls', [
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'سالن سینما',
        ]);
    }

    public function test_hall_type_catalog_can_add_custom_type(): void
    {
        $name = app(HallTypeCatalogService::class)->normalize('آمفی تئاتر');

        $this->actingAs($this->admin)
            ->postJson(route('api.hall-types.store'), ['name' => $name])
            ->assertOk()
            ->assertJsonPath('name', $name);

        $this->assertDatabaseHas('hall_types', ['name' => $name]);
    }

    public function test_hall_amenity_catalog_can_add_and_remove_custom_amenity(): void
    {
        $name = app(HallAmenityCatalogService::class)->normalize('ترجمه همزمان');

        $this->actingAs($this->admin)
            ->postJson(route('api.hall-amenities.store'), ['name' => $name])
            ->assertOk()
            ->assertJsonPath('name', $name);

        $amenity = HallAmenity::query()->where('name', $name)->first();
        $this->assertNotNull($amenity);

        $this->actingAs($this->admin)
            ->deleteJson(route('api.hall-amenities.destroy', $amenity))
            ->assertOk();

        $this->assertDatabaseMissing('hall_amenities', ['id' => $amenity->id]);
    }

    public function test_default_hall_types_are_seeded_on_first_catalog_read(): void
    {
        $names = app(HallTypeCatalogService::class)->allOrdered()->pluck('name')->all();

        $this->assertContains('کنفرانس', $names);
        $this->assertContains('همایش', $names);
        $this->assertContains('سینما', $names);
    }

    public function test_default_hall_amenities_are_seeded_on_first_catalog_read(): void
    {
        $names = app(HallAmenityCatalogService::class)->names();

        $this->assertContains('ویدئو پروژکتور', $names);
        $this->assertContains('سیستم صوتی', $names);
    }

    public function test_hall_without_programs_can_be_deleted(): void
    {
        $hall = Hall::create([
            'accommodation_id' => $this->accommodation->id,
            'hall_type_id'     => $this->hallType->id,
            'name'             => 'سالن قابل حذف',
            'capacity'         => 40,
            'is_active'        => true,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.halls.destroy', [$this->accommodation, $hall]))
            ->assertRedirect(route('admin.halls.accommodation.index', $this->accommodation));

        $this->assertDatabaseMissing('halls', ['id' => $hall->id]);
    }

    public function test_hall_with_upcoming_program_cannot_be_deleted(): void
    {
        $hall = Hall::create([
            'accommodation_id' => $this->accommodation->id,
            'hall_type_id'     => $this->hallType->id,
            'name'             => 'سالن رزرو شده',
            'capacity'         => 60,
            'is_active'        => true,
        ]);

        $checkIn = now()->addDays(8)->toDateString();
        $booking = Booking::create([
            'user_id'          => $this->admin->id,
            'accommodation_id' => $this->accommodation->id,
            'check_in'         => $checkIn,
            'check_out'        => now()->addDays(9)->toDateString(),
            'guests'           => 10,
            'nights'           => 1,
            'base_price'       => 1_000_000,
            'total_price'      => 1_000_000,
            'status'           => 'confirmed',
            'booking_source'   => 'program',
            'tracking_code'    => 'HALLDEL01',
        ]);

        Program::create([
            'booking_id'        => $booking->id,
            'accommodation_id'  => $this->accommodation->id,
            'created_by'        => $this->admin->id,
            'title'             => 'رویداد آینده',
            'program_type'      => Program::TYPE_HALL,
            'hall_id'           => $hall->id,
            'hall_start_time'   => '10:00:00',
            'hall_end_time'     => '12:00:00',
            'guest_count'       => 10,
            'rooms_allocated'   => 0,
            'status'            => Program::STATUS_ACTIVE,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.halls.destroy', [$this->accommodation, $hall]))
            ->assertRedirect(route('admin.halls.accommodation.index', $this->accommodation))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('halls', ['id' => $hall->id]);
    }
}
