<?php

namespace Tests;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Services\VeteranPolicyProvisioner;
use App\Services\VeteranPolicyService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function createTestAccommodation(array $overrides = []): Accommodation
    {
        $provinceId = DB::table('provinces')->insertGetId([
            'name'       => 'استان تست',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name'        => 'شهر تست',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $accommodation = Accommodation::create(array_merge([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه تست',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ], $overrides));

        app(VeteranPolicyProvisioner::class)->seedForAccommodation($accommodation);

        return $accommodation;
    }

    protected function veteranCatalog(Accommodation $accommodation, string $key): ServiceCatalog
    {
        return ServiceCatalog::query()
            ->where('accommodation_id', $accommodation->id)
            ->where('key', $key)
            ->firstOrFail();
    }

    protected function veteranPolicyFor(Accommodation $accommodation): VeteranPolicyService
    {
        return app(VeteranPolicyService::class)->forAccommodation($accommodation->id);
    }
}
