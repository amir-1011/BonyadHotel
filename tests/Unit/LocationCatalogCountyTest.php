<?php

namespace Tests\Unit;

use App\Models\County;
use App\Services\LocationCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationCatalogCountyTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_or_create_county_creates_province_and_county_when_missing(): void
    {
        $service = app(LocationCatalogService::class);

        $result = $service->resolveOrCreateCounty('گیلان', 'رشت');

        $this->assertTrue($result['province_created']);
        $this->assertTrue($result['county_created']);
        $this->assertSame('گیلان', $result['province_name']);
        $this->assertSame('رشت', $result['county_name']);
        $this->assertDatabaseHas('counties', ['name' => 'رشت']);
    }

    public function test_find_county_id_returns_existing_county(): void
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name'       => 'مازندران',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $countyId = County::create([
            'province_id' => $provinceId,
            'name'        => 'ساری',
        ])->id;

        $found = app(LocationCatalogService::class)->findCountyId('مازندران', 'ساری');

        $this->assertSame($countyId, $found);
    }

    public function test_resolve_or_create_county_is_idempotent(): void
    {
        $service = app(LocationCatalogService::class);

        $first = $service->resolveOrCreateCounty('فارس', 'مرودشت');
        $second = $service->resolveOrCreateCounty('فارس', 'مرودشت');

        $this->assertSame($first['id'], $second['id']);
        $this->assertFalse($second['province_created']);
        $this->assertFalse($second['county_created']);
        $this->assertSame(1, County::where('name', 'مرودشت')->count());
    }
}
