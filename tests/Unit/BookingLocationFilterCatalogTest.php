<?php

namespace Tests\Unit;

use App\Models\Accommodation;
use App\Models\County;
use App\Support\BookingLocationFilterCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingLocationFilterCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_cities_and_counties_empty_without_province(): void
    {
        $catalog = app(BookingLocationFilterCatalog::class);

        $this->assertTrue($catalog->cities('', null)->isEmpty());
        $this->assertTrue($catalog->counties('', null)->isEmpty());
    }

    public function test_cities_and_counties_narrow_by_province(): void
    {
        $provinceAId = DB::table('provinces')->insertGetId([
            'name' => 'تهران', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $provinceBId = DB::table('provinces')->insertGetId([
            'name' => 'گیلان', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $cityAId = DB::table('cities')->insertGetId([
            'province_id' => $provinceAId, 'name' => 'تهران', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cities')->insert([
            'province_id' => $provinceBId, 'name' => 'رشت', 'created_at' => now(), 'updated_at' => now(),
        ]);

        County::create(['province_id' => $provinceAId, 'name' => 'شمیرانات']);
        County::create(['province_id' => $provinceBId, 'name' => 'لنگرود']);

        $catalog = app(BookingLocationFilterCatalog::class);

        $this->assertSame([$cityAId], $catalog->cities((string) $provinceAId)->pluck('id')->all());
        $this->assertSame(['شمیرانات'], $catalog->counties((string) $provinceAId)->pluck('name')->all());
        $this->assertSame(['لنگرود'], $catalog->counties((string) $provinceBId)->pluck('name')->all());
    }

    public function test_host_scope_limits_provinces(): void
    {
        $provinceAId = DB::table('provinces')->insertGetId([
            'name' => 'تهران', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $provinceBId = DB::table('provinces')->insertGetId([
            'name' => 'اصفهان', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $cityAId = DB::table('cities')->insertGetId([
            'province_id' => $provinceAId, 'name' => 'تهران', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $cityBId = DB::table('cities')->insertGetId([
            'province_id' => $provinceBId, 'name' => 'اصفهان', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $accA = Accommodation::create([
            'city_id' => $cityAId, 'name' => 'هتل الف', 'price_per_night' => 1_000_000,
            'capacity' => 10, 'rooms' => 5, 'is_active' => true,
        ]);

        $catalog = app(BookingLocationFilterCatalog::class);
        $provinces = $catalog->provinces([$accA->id]);

        $this->assertCount(1, $provinces);
        $this->assertSame($provinceAId, $provinces->first()->id);
        $this->assertTrue($catalog->cities((string) $provinceBId, [$accA->id])->isEmpty());
    }
}
