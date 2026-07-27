<?php

namespace Tests\Unit;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Services\ProvinceAccountingCodeService;
use App\Support\ProvinceAccountingIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProvinceAccountingCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    private Province $province;

    private ProvinceAccountingCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->province = Province::query()->create([
            'name'            => 'مازندران',
            'accounting_code' => '515',
        ]);

        $this->service = app(ProvinceAccountingCodeService::class);
    }

    public function test_assigns_sequential_beneficiary_codes(): void
    {
        $first = $this->service->assignNext($this->province, ProvinceAccountingIndicators::BENEFICIARY);
        ProgramBeneficiary::create([
            'province_id'             => $this->province->id,
            'name'                    => 'ذینفع ۱',
            'beneficiary_code'        => $first,
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121111111',
        ]);

        $second = $this->service->assignNext($this->province, ProvinceAccountingIndicators::BENEFICIARY);

        $this->assertSame('515101', $first);
        $this->assertSame('515102', $second);
    }

    public function test_assigns_sequential_organization_codes(): void
    {
        $first = $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION);
        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'ارگان ۱',
            'employer_code'           => $first,
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121111111',
        ]);

        $second = $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION);

        $this->assertSame('515401', $first);
        $this->assertSame('515402', $second);
    }

    public function test_assigns_sequential_personnel_codes(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        $first = $this->service->assignNext($this->province, ProvinceAccountingIndicators::PERSONNEL);
        User::create([
            'name'           => 'پرسنل ۱',
            'mobile'         => '09121111111',
            'province_id'    => $this->province->id,
            'personnel_code' => $first,
        ]);

        $second = $this->service->assignNext($this->province, ProvinceAccountingIndicators::PERSONNEL);

        $this->assertSame('515701', $first);
        $this->assertSame('515702', $second);
    }

    public function test_counters_are_independent_per_indicator(): void
    {
        ProgramBeneficiary::create([
            'province_id'             => $this->province->id,
            'name'                    => 'رستوران حاج حسین',
            'beneficiary_code'        => '515101',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121111111',
        ]);

        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'بنیاد شهید',
            'employer_code'           => '515401',
            'national_or_economic_id' => '2234567890',
            'mobile'                  => '09122222222',
        ]);

        $this->assertSame('515102', $this->service->assignNext($this->province, ProvinceAccountingIndicators::BENEFICIARY));
        $this->assertSame('515402', $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION));
        $this->assertSame('515701', $this->service->assignNext($this->province, ProvinceAccountingIndicators::PERSONNEL));
    }

    public function test_existing_codes_increase_next_counter(): void
    {
        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'شهرداری',
            'employer_code'           => '515403',
            'national_or_economic_id' => '3234567890',
            'mobile'                  => '09123333333',
        ]);

        $this->assertSame('515404', $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION));
    }

    public function test_personnel_existing_codes_are_counted(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);

        User::create([
            'name'           => 'علی اصغر مسلمی',
            'mobile'         => '09124444444',
            'province_id'    => $this->province->id,
            'personnel_code' => '515701',
        ]);

        $this->assertSame('515702', $this->service->assignNext($this->province, ProvinceAccountingIndicators::PERSONNEL));
    }

    public function test_parse_accounting_code(): void
    {
        $parsed = $this->service->parseAccountingCode('515401');

        $this->assertSame([
            'province_code' => '515',
            'indicator'     => 4,
            'counter'       => 1,
            'full'          => '515401',
        ], $parsed);
    }

    public function test_preview_next_matches_assign_when_empty(): void
    {
        $preview = $this->service->previewNext($this->province, ProvinceAccountingIndicators::BENEFICIARY);
        $assigned = $this->service->assignNext($this->province, ProvinceAccountingIndicators::BENEFICIARY);

        $this->assertSame('515101', $preview);
        $this->assertSame('515101', $assigned);
    }

    public function test_counters_are_isolated_per_province(): void
    {
        $tehran = Province::create(['name' => 'تهران', 'accounting_code' => '508']);

        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'ارگان مازندران',
            'employer_code'           => '515405',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09120000001',
        ]);

        $this->assertSame('515406', $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION));
        $this->assertSame('508401', $this->service->assignNext($tehran, ProvinceAccountingIndicators::ORGANIZATION));
    }

    public function test_legacy_non_standard_codes_do_not_affect_counter(): void
    {
        ProgramBeneficiary::create([
            'province_id'             => $this->province->id,
            'name'                    => 'ذینفع قدیمی',
            'beneficiary_code'        => 'MB-001',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121111111',
        ]);

        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'کارفرمای قدیمی',
            'employer_code'           => 'EMP-001',
            'national_or_economic_id' => '2234567890',
            'mobile'                  => '09122222222',
        ]);

        $this->assertSame('515101', $this->service->assignNext($this->province, ProvinceAccountingIndicators::BENEFICIARY));
        $this->assertSame('515401', $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION));
    }

    public function test_counter_overflow_beyond_two_digits(): void
    {
        ProgramBeneficiary::create([
            'province_id'             => $this->province->id,
            'name'                    => 'ذینفع ۹۹',
            'beneficiary_code'        => '515199',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121111111',
        ]);

        $this->assertSame('5151100', $this->service->assignNext($this->province, ProvinceAccountingIndicators::BENEFICIARY));
    }

    public function test_ensure_province_has_code_resolves_from_catalog(): void
    {
        $province = Province::create(['name' => 'گیلان']);

        $resolved = $this->service->ensureProvinceHasCode($province);

        $this->assertSame('526', $resolved->accounting_code);
    }

    public function test_ensure_province_has_code_throws_for_unknown_province(): void
    {
        $province = Province::create(['name' => 'استان ناشناخته تست']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('کد حسابداری برای استان');

        $this->service->ensureProvinceHasCode($province);
    }

    public function test_invalid_indicator_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->assignNext($this->province, 2);
    }

    public function test_is_accounting_code_validation(): void
    {
        $this->assertTrue($this->service->isAccountingCode('515401'));
        $this->assertTrue($this->service->isAccountingCode('515 401'));
        $this->assertFalse($this->service->isAccountingCode('1234567890'));
        $this->assertFalse($this->service->isAccountingCode('EMP-001'));
        $this->assertFalse($this->service->isAccountingCode('51540'));
    }

    public function test_parse_rejects_reserved_indicators(): void
    {
        $this->assertNull($this->service->parseAccountingCode('515201'));
        $this->assertNull($this->service->parseAccountingCode('515301'));
    }

    public function test_parse_handles_three_digit_counter(): void
    {
        $parsed = $this->service->parseAccountingCode('5151100');

        $this->assertNotNull($parsed);
        $this->assertSame(100, $parsed['counter']);
        $this->assertSame(1, $parsed['indicator']);
    }

    public function test_assign_next_skips_existing_organization_code_in_same_sequence(): void
    {
        ProgramEmployer::create([
            'province_id'             => $this->province->id,
            'name'                    => 'اول',
            'employer_code'           => '515401',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09120000001',
        ]);

        $this->assertSame('515402', $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION));
    }

    public function test_cross_table_collision_is_detected(): void
    {
        ProgramBeneficiary::create([
            'province_id'             => $this->province->id,
            'name'                    => 'ذینفع',
            'beneficiary_code'        => '515401',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09121111111',
        ]);

        $this->expectException(RuntimeException::class);

        $this->service->assignNext($this->province, ProvinceAccountingIndicators::ORGANIZATION);
    }
}
