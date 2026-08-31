<?php

namespace Tests\Feature;

use App\Http\Resources\Api\AccommodationResource;
use App\Livewire\Admin\AccommodationEdit;
use App\Models\Accommodation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccommodationFeaturedImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $cityId;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $this->admin = User::create(['name' => 'ادمین', 'mobile' => '09100000099']);
        $this->admin->assignRole('super_admin');

        $provinceId = DB::table('provinces')->insertGetId([
            'name'       => 'تهران',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'تهران',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        (new \Database\Seeders\AccommodationTypeSeeder())->run();
    }

    public function test_admin_can_set_featured_image_on_update(): void
    {
        $accommodation = Accommodation::create([
            'city_id'           => $this->cityId,
            'management_status' => 'outsourced',
            'type'              => 'hotel',
            'name'              => 'هتل نمونه',
            'price_per_night'   => 1_000_000,
            'capacity'          => 10,
            'rooms'             => 5,
            'is_active'         => true,
            'images'            => ['accommodations/a.webp', 'accommodations/b.webp'],
            'image'             => 'accommodations/a.webp',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(AccommodationEdit::class, ['accommodation' => $accommodation])
            ->call('setFeaturedImage', 'accommodations/b.webp')
            ->assertSet('image', 'accommodations/b.webp')
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.accommodations.index'));

        $fresh = $accommodation->fresh();
        $this->assertSame('accommodations/b.webp', $fresh->image);

        $payload = (new AccommodationResource($fresh))->toArray(Request::create('/'));
        $this->assertStringContainsString('accommodations/b.webp', $payload['image']);
    }

    public function test_removing_featured_image_clears_image_field_on_update(): void
    {
        $accommodation = Accommodation::create([
            'city_id'           => $this->cityId,
            'management_status' => 'outsourced',
            'type'              => 'hotel',
            'name'              => 'هتل نمونه',
            'price_per_night'   => 1_000_000,
            'capacity'          => 10,
            'rooms'             => 5,
            'is_active'         => true,
            'images'            => ['accommodations/a.webp', 'accommodations/b.webp'],
            'image'             => 'accommodations/a.webp',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(AccommodationEdit::class, ['accommodation' => $accommodation])
            ->call('removeExistingImage', 'accommodations/a.webp')
            ->assertSet('image', '')
            ->call('update')
            ->assertHasNoErrors();

        $fresh = $accommodation->fresh();
        $this->assertNull($fresh->image);
        $this->assertSame(['accommodations/b.webp'], $fresh->images);
    }
}
