<?php

namespace Tests\Unit;

use App\Models\Accommodation;
use App\Models\County;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Support\BookingServiceFilterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingServiceFilterCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_services_narrow_by_accommodation(): void
    {
        [$accA, $poolA] = $this->accommodationWithPool('هتل آلفا');
        [$accB, $poolB] = $this->accommodationWithPool('هتل بتا');

        $catalog = app(BookingServiceFilterCatalog::class);
        $services = $catalog->parentServices((string) $accA->id, '', '', '');

        $this->assertCount(1, $services);
        $this->assertSame($poolA->id, $services->first()->id);
        $this->assertNotSame($poolB->id, $services->first()->id);
    }

    public function test_parent_services_narrow_by_city(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'استان', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cityAId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'name' => 'شهر الف', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cityBId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'name' => 'شهر ب', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $accA = Accommodation::create([
            'city_id' => $cityAId, 'name' => 'الفا', 'price_per_night' => 1_000_000,
            'capacity' => 10, 'rooms' => 5, 'is_active' => true,
        ]);
        $accB = Accommodation::create([
            'city_id' => $cityBId, 'name' => 'بتا', 'price_per_night' => 1_000_000,
            'capacity' => 10, 'rooms' => 5, 'is_active' => true,
        ]);

        $poolA = ServiceCatalog::create([
            'accommodation_id' => $accA->id, 'key' => 'pool_a', 'name' => 'استخر', 'is_active' => true,
        ]);
        ServiceCatalog::create([
            'accommodation_id' => $accB->id, 'key' => 'pool_b', 'name' => 'استخر', 'is_active' => true,
        ]);

        $services = app(BookingServiceFilterCatalog::class)->parentServices('', '', (string) $cityAId, '');

        $this->assertCount(1, $services);
        $this->assertSame($poolA->id, $services->first()->id);
    }

    public function test_variants_load_for_parent_service(): void
    {
        [$acc, $pool] = $this->accommodationWithPool('هتل');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'              => 'active_pool',
            'name'             => 'استخر نشاط',
            'price'            => 500_000,
            'is_active'        => true,
        ]);

        $variants = app(BookingServiceFilterCatalog::class)->variants((string) $pool->id);

        $this->assertCount(1, $variants);
        $this->assertSame($variant->id, $variants->first()->id);
    }

    /** @return array{0: Accommodation, 1: ServiceCatalog} */
    private function accommodationWithPool(string $name): array
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name' => 'استان', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId, 'name' => 'شهر', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $acc = Accommodation::create([
            'city_id' => $cityId, 'name' => $name, 'price_per_night' => 1_000_000,
            'capacity' => 10, 'rooms' => 5, 'is_active' => true,
        ]);

        $pool = ServiceCatalog::create([
            'accommodation_id' => $acc->id,
            'key'            => 'pool_' . $acc->id,
            'name'           => 'استخر',
            'is_active'      => true,
        ]);

        return [$acc, $pool];
    }
}
