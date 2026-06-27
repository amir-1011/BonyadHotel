<?php

namespace Tests\Feature;

use App\Livewire\Admin\AccommodationCreate;
use App\Livewire\Admin\AccommodationEdit;
use App\Livewire\Admin\LocationCatalogSettings;
use App\Models\Accommodation;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccommodationCountyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $provinceId;

    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $this->admin = User::create(['name' => 'ادمین', 'mobile' => '09100000001']);
        $this->admin->assignRole('super_admin');

        $this->provinceId = DB::table('provinces')->insertGetId([
            'name'       => 'تهران',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->cityId = DB::table('cities')->insertGetId([
            'province_id' => $this->provinceId,
            'name'        => 'تهران',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        (new \Database\Seeders\AccommodationTypeSeeder())->run();
    }

    private function baseAccommodationFields(): array
    {
        return [
            'managementStatus' => 'outsourced',
            'name'             => 'هتل تست',
            'type'             => 'hotel',
            'pricePerNight'    => 1_000_000,
            'capacity'         => 10,
            'rooms'            => 5,
        ];
    }

    public function test_admin_can_create_accommodation_with_optional_county(): void
    {
        $countyId = County::create([
            'province_id' => $this->provinceId,
            'name'        => 'شمیرانات',
        ])->id;

        $this->actingAs($this->admin);

        Livewire::test(AccommodationCreate::class)
            ->set('provinceId', $this->provinceId)
            ->set('cityId', $this->cityId)
            ->set('countyId', $countyId)
            ->set($this->baseAccommodationFields())
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.accommodations.index'));

        $this->assertDatabaseHas('accommodations', [
            'name'      => 'هتل تست',
            'city_id'   => $this->cityId,
            'county_id' => $countyId,
        ]);
    }

    public function test_admin_can_create_accommodation_without_county(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AccommodationCreate::class)
            ->set('provinceId', $this->provinceId)
            ->set('cityId', $this->cityId)
            ->set('countyId', 0)
            ->set($this->baseAccommodationFields())
            ->set('name', 'هتل بدون شهرستان')
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accommodations', [
            'name'      => 'هتل بدون شهرستان',
            'city_id'   => $this->cityId,
            'county_id' => null,
        ]);
    }

    public function test_admin_can_add_new_county_from_form(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AccommodationCreate::class)
            ->set('provinceId', $this->provinceId)
            ->call('toggleAddCounty')
            ->set('newCountyName', 'ری')
            ->call('addCounty')
            ->assertHasNoErrors()
            ->assertSet('countyId', County::where('name', 'ری')->value('id'));

        $this->assertDatabaseHas('counties', [
            'province_id' => $this->provinceId,
            'name'        => 'ری',
        ]);
    }

    public function test_changing_province_resets_city_and_county(): void
    {
        $otherProvinceId = DB::table('provinces')->insertGetId([
            'name'       => 'اصفهان',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $countyId = County::create([
            'province_id' => $this->provinceId,
            'name'        => 'شمیرانات',
        ])->id;

        $this->actingAs($this->admin);

        Livewire::test(AccommodationCreate::class)
            ->set('provinceId', $this->provinceId)
            ->set('cityId', $this->cityId)
            ->set('countyId', $countyId)
            ->set('provinceId', $otherProvinceId)
            ->assertSet('cityId', 0)
            ->assertSet('countyId', 0);
    }

    public function test_county_must_belong_to_selected_province(): void
    {
        $otherProvinceId = DB::table('provinces')->insertGetId([
            'name'       => 'اصفهان',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $foreignCountyId = County::create([
            'province_id' => $otherProvinceId,
            'name'        => 'کاشان',
        ])->id;

        $this->actingAs($this->admin);

        Livewire::test(AccommodationCreate::class)
            ->set('provinceId', $this->provinceId)
            ->set('cityId', $this->cityId)
            ->set('countyId', $foreignCountyId)
            ->set($this->baseAccommodationFields())
            ->set('name', 'هتل نامعتبر')
            ->call('store')
            ->assertHasErrors(['countyId']);
    }

    public function test_admin_can_update_accommodation_county(): void
    {
        $accommodation = Accommodation::create([
            'city_id'           => $this->cityId,
            'management_status' => 'outsourced',
            'type'              => 'hotel',
            'name'              => 'هتل ویرایش',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $countyId = County::create([
            'province_id' => $this->provinceId,
            'name'        => 'شهریار',
        ])->id;

        $this->actingAs($this->admin);

        Livewire::test(AccommodationEdit::class, ['accommodation' => $accommodation])
            ->set('countyId', $countyId)
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.accommodations.index'));

        $this->assertSame($countyId, $accommodation->fresh()->county_id);
    }

    public function test_admin_can_clear_county_on_update(): void
    {
        $countyId = County::create([
            'province_id' => $this->provinceId,
            'name'        => 'دماوند',
        ])->id;

        $accommodation = Accommodation::create([
            'city_id'           => $this->cityId,
            'county_id'         => $countyId,
            'management_status' => 'outsourced',
            'type'              => 'hotel',
            'name'              => 'هتل با شهرستان',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(AccommodationEdit::class, ['accommodation' => $accommodation])
            ->set('countyId', 0)
            ->call('update')
            ->assertHasNoErrors();

        $this->assertNull($accommodation->fresh()->county_id);
    }

    public function test_duplicate_county_name_in_same_province_is_rejected(): void
    {
        County::create([
            'province_id' => $this->provinceId,
            'name'        => 'شمیرانات',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(AccommodationCreate::class)
            ->set('provinceId', $this->provinceId)
            ->call('toggleAddCounty')
            ->set('newCountyName', 'شمیرانات')
            ->call('addCounty')
            ->assertHasErrors(['newCountyName']);
    }

    public function test_county_in_use_cannot_be_deleted_from_catalog(): void
    {
        $county = County::create([
            'province_id' => $this->provinceId,
            'name'        => 'فیروزکوه',
        ]);

        Accommodation::create([
            'city_id'           => $this->cityId,
            'county_id'         => $county->id,
            'management_status' => 'outsourced',
            'type'              => 'hotel',
            'name'              => 'هتل فیروزکوه',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $this->actingAs($this->admin);

        Livewire::test(LocationCatalogSettings::class)
            ->call('deleteCounty', $county->id);

        $this->assertDatabaseHas('counties', ['id' => $county->id]);
    }

    public function test_unused_county_can_be_deleted_from_catalog(): void
    {
        $county = County::create([
            'province_id' => $this->provinceId,
            'name'        => 'پردیس',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(LocationCatalogSettings::class)
            ->call('deleteCounty', $county->id);

        $this->assertDatabaseMissing('counties', ['id' => $county->id]);
    }
}
