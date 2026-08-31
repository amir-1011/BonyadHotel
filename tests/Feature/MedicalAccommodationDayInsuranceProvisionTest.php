<?php

namespace Tests\Feature;

use App\Models\MedicalAccommodationSetting;
use App\Models\ProgramEmployer;
use App\Services\MedicalAccommodationProvisioner;
use App\Support\MedicalAccommodationTariffs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicalAccommodationDayInsuranceProvisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_province_gets_its_own_day_insurance_employer(): void
    {
        $farsProvinceId = $this->ensureTestProvinceId('فارس', '518');
        $farsCityId = $this->ensureTestCityId($farsProvinceId, 'شیراز');
        $farsAccommodation = $this->createTestAccommodation([
            'city_id' => $farsCityId,
            'name'    => 'اقامتگاه فارس',
        ]);

        $tehranProvinceId = $this->ensureTestProvinceId('تهران', '508');
        $tehranCityId = $this->ensureTestCityId($tehranProvinceId, 'تهران');
        $tehranAccommodation = $this->createTestAccommodation([
            'city_id' => $tehranCityId,
            'name'    => 'اقامتگاه تهران',
        ]);

        $farsEmployer = $farsAccommodation->fresh()->medicalAccommodationSetting?->employer;
        $tehranEmployer = $tehranAccommodation->fresh()->medicalAccommodationSetting?->employer;

        $this->assertNotNull($farsEmployer);
        $this->assertNotNull($tehranEmployer);
        $this->assertSame(MedicalAccommodationTariffs::employerNameForProvince('فارس'), $farsEmployer->name);
        $this->assertSame(MedicalAccommodationTariffs::employerNameForProvince('تهران'), $tehranEmployer->name);
        $this->assertNotSame($farsEmployer->id, $tehranEmployer->id);
        $this->assertSame($farsProvinceId, $farsEmployer->province_id);
        $this->assertSame($tehranProvinceId, $tehranEmployer->province_id);
        $this->assertStringStartsWith('5184', (string) $farsEmployer->employer_code);
        $this->assertStringStartsWith('5084', (string) $tehranEmployer->employer_code);
        $this->assertStringContainsString('تهران', $tehranEmployer->displayLabel());
        $this->assertStringContainsString('فارس', $farsEmployer->displayLabel());
    }

    public function test_accommodations_in_the_same_province_share_one_day_insurance_employer(): void
    {
        $first = $this->createTestAccommodation(['name' => 'اقامتگاه اول']);
        $second = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);

        $employerId = (int) $first->fresh()->medicalAccommodationSetting?->program_employer_id;

        $this->assertSame(
            $employerId,
            (int) $second->fresh()->medicalAccommodationSetting?->program_employer_id,
        );
        $this->assertSame(
            $first->resolvedProvince()?->id,
            (int) ProgramEmployer::query()->findOrFail($employerId)->province_id,
        );
        $this->assertSame(
            1,
            ProgramEmployer::query()
                ->where('province_id', $first->resolvedProvince()?->id)
                ->where(function ($query) {
                    $query->where('name', MedicalAccommodationTariffs::EMPLOYER_NAME)
                        ->orWhere('name', 'like', MedicalAccommodationTariffs::EMPLOYER_NAME.' %');
                })
                ->count(),
        );
    }

    public function test_wrong_province_day_insurance_is_replaced_on_seed(): void
    {
        $farsAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه فارس']);
        $farsEmployerId = (int) $farsAccommodation->fresh()->medicalAccommodationSetting?->program_employer_id;

        $tehranProvinceId = $this->ensureTestProvinceId('تهران', '508');
        $tehranCityId = $this->ensureTestCityId($tehranProvinceId, 'تهران');
        $tehranAccommodation = $this->createTestAccommodation([
            'city_id' => $tehranCityId,
            'name'    => 'اقامتگاه تهران',
        ]);

        MedicalAccommodationSetting::query()
            ->where('accommodation_id', $tehranAccommodation->id)
            ->update(['program_employer_id' => $farsEmployerId]);

        $setting = app(MedicalAccommodationProvisioner::class)
            ->seedForAccommodation($tehranAccommodation->fresh(['city.province']));

        $this->assertNotSame($farsEmployerId, (int) $setting->program_employer_id);
        $this->assertSame($tehranProvinceId, (int) $setting->employer?->province_id);
        $this->assertSame(MedicalAccommodationTariffs::employerNameForProvince('تهران'), $setting->employer?->name);
    }

    public function test_custom_employer_is_not_replaced(): void
    {
        $accommodation = $this->createTestAccommodation();
        $provinceId = $accommodation->resolvedProvince()?->id;

        $custom = ProgramEmployer::create([
            'province_id'             => $provinceId,
            'name'                    => 'بیمه ایران',
            'employer_code'           => '515499',
            'national_or_economic_id' => '3344556677',
            'mobile'                  => '09127778899',
        ]);

        MedicalAccommodationSetting::query()
            ->where('accommodation_id', $accommodation->id)
            ->update(['program_employer_id' => $custom->id]);

        $setting = app(MedicalAccommodationProvisioner::class)
            ->seedForAccommodation($accommodation->fresh(['city.province']));

        $this->assertSame($custom->id, (int) $setting->program_employer_id);
    }

    public function test_sync_creates_day_insurance_for_province_without_accommodation(): void
    {
        $this->createTestAccommodation();
        $tehranProvinceId = $this->ensureTestProvinceId('تهران', '508');

        $result = app(MedicalAccommodationProvisioner::class)->syncDayInsuranceEmployers();

        $tehranEmployer = ProgramEmployer::query()
            ->where('province_id', $tehranProvinceId)
            ->where('name', MedicalAccommodationTariffs::employerNameForProvince('تهران'))
            ->first();

        $this->assertNotNull($tehranEmployer);
        $this->assertGreaterThanOrEqual(1, $result['created']);
        $this->assertStringStartsWith('5084', (string) $tehranEmployer->employer_code);
    }

    public function test_sync_renames_plain_day_insurance_to_include_province(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();
        $employer = $accommodation->fresh(['medicalAccommodationSetting.employer.user', 'city.province'])
            ->medicalAccommodationSetting
            ?->employer;

        $this->assertNotNull($employer);
        $employer->update(['name' => MedicalAccommodationTariffs::EMPLOYER_NAME]);
        $employer->user?->update(['name' => MedicalAccommodationTariffs::EMPLOYER_NAME]);

        app(MedicalAccommodationProvisioner::class)->syncDayInsuranceEmployers();

        $employer->refresh()->load('user');
        $expected = MedicalAccommodationTariffs::employerNameForProvince($accommodation->resolvedProvince()?->name);
        $this->assertSame($expected, $employer->name);
        $this->assertSame($expected, $employer->user?->name);
    }
}
