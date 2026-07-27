<?php

namespace Tests;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Services\VeteranPolicyProvisioner;
use App\Services\VeteranPolicyService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\AssertionFailedError;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->refuseTestsWhenConfigIsCached();
        $this->refuseTestsUnlessSqliteInMemory();

        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->assertRuntimeDatabaseIsSqliteInMemory();
    }

    /**
     * Cached config can override phpunit.xml and point tests at MySQL (migrate:fresh wipes all data).
     */
    private function refuseTestsWhenConfigIsCached(): void
    {
        $cachedConfig = dirname(__DIR__) . '/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            throw new AssertionFailedError(
                'Tests blocked: bootstrap/cache/config.php exists. '
                . 'Run "php artisan optimize:clear" before "php artisan test" '
                . 'so tests use sqlite :memory: instead of your real database.'
            );
        }
    }

    /**
     * Runs before the app boots — uses phpunit.xml env, not cached config.
     */
    private function refuseTestsUnlessSqliteInMemory(): void
    {
        $connection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? null);
        $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? null);

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new AssertionFailedError(
                'Tests blocked: DB must be sqlite :memory: (from phpunit.xml). '
                . "Got [{$connection}:{$database}]. Never run tests against bonyadyar."
            );
        }
    }

    private function assertRuntimeDatabaseIsSqliteInMemory(): void
    {
        if (config('app.env') !== 'testing') {
            $this->fail('Tests must run with APP_ENV=testing. Use: php artisan test');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            $this->fail(
                "Tests blocked after boot: database is [{$connection}:{$database}]. "
                . 'Run "php artisan optimize:clear" then "php artisan test".'
            );
        }
    }

    protected function createTestAccommodation(array $overrides = []): Accommodation
    {
        $provinceId = $this->ensureTestProvinceId();
        $cityId = $this->ensureTestCityId($provinceId);

        $accommodation = Accommodation::create(array_merge([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه تست',
            'price_per_night' => 1_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ], $overrides));

        app(VeteranPolicyProvisioner::class)->seedForAccommodation($accommodation);
        app(\App\Services\CancellationPolicyProvisioner::class)->seedForAccommodation($accommodation);

        return $accommodation;
    }

    protected function ensureTestProvinceId(
        string $name = 'استان تست',
        string $accountingCode = '515',
    ): int {
        $row = DB::table('provinces')->where('name', $name)->first();

        if ($row) {
            if (blank($row->accounting_code)) {
                DB::table('provinces')->where('id', $row->id)->update(['accounting_code' => $accountingCode]);
            }

            return (int) $row->id;
        }

        return (int) DB::table('provinces')->insertGetId([
            'name'            => $name,
            'accounting_code' => $accountingCode,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    protected function ensureTestCityId(
        int $provinceId,
        string $name = 'شهر تست',
    ): int {
        $row = DB::table('cities')
            ->where('province_id', $provinceId)
            ->where('name', $name)
            ->first();

        if ($row) {
            return (int) $row->id;
        }

        return (int) DB::table('cities')->insertGetId([
            'province_id' => $provinceId,
            'name'        => $name,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
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
