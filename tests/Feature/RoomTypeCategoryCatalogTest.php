<?php

namespace Tests\Feature;

use App\Models\Accommodation;
use App\Models\RoomType;
use App\Models\RoomTypeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomTypeCategoryCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name'   => 'ادمین کاتالوگ',
            'mobile' => '09000000666',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_can_rename_room_type_category_and_propagate_to_room_types(): void
    {
        $category = RoomTypeCategory::create(['name' => 'دبل', 'sort_order' => 0]);

        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر', 'created_at' => now(), 'updated_at' => now()]);
        $accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه',
            'price_per_night' => 100_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'اتاق ۱',
            'bed_type'         => 'دبل',
            'capacity'         => 2,
            'room_count'       => 1,
            'is_active'        => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('api.room-type-categories.update', $category), ['name' => 'دبل بزرگ']);

        $response->assertOk()
            ->assertJson([
                'id'         => $category->id,
                'name'       => 'دبل بزرگ',
                'old_name'   => 'دبل',
                'can_edit'   => true,
                'can_delete' => true,
            ]);

        $this->assertDatabaseHas('room_type_categories', ['id' => $category->id, 'name' => 'دبل بزرگ']);
        $this->assertDatabaseHas('room_types', ['id' => $roomType->id, 'bed_type' => 'دبل بزرگ']);
    }

    public function test_host_cannot_rename_seeded_category(): void
    {
        $category = RoomTypeCategory::create(['name' => 'تویین', 'sort_order' => 0, 'created_by' => null]);

        $host = User::create(['name' => 'میزبان', 'mobile' => '09000000555']);
        $host->assignRole('host');

        $this->actingAs($host)
            ->patchJson(route('api.room-type-categories.update', $category), ['name' => 'تویین جدید'])
            ->assertForbidden();
    }

    public function test_admin_room_type_edit_page_shows_rename_button_for_categories(): void
    {
        $provinceId = DB::table('provinces')->insertGetId(['name' => 'استان', 'created_at' => now(), 'updated_at' => now()]);
        $cityId = DB::table('cities')->insertGetId(['province_id' => $provinceId, 'name' => 'شهر', 'created_at' => now(), 'updated_at' => now()]);
        $accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه',
            'price_per_night' => 100_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'اتاق استاندارد',
            'bed_type'         => 'سوئیت',
            'capacity'         => 2,
            'room_count'       => 1,
            'is_active'        => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.room-types.edit', [$accommodation, $roomType]));

        $response->assertOk();
        $response->assertSee('data-action="rename-room-category"', false);
        $response->assertSee('data-category-update-url', false);
    }
}
