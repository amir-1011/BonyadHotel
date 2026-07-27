<?php

namespace Tests\Feature;

use App\Livewire\Admin\HostCreate;
use App\Livewire\Admin\LocationCatalogSettings;
use App\Livewire\Admin\UserShow;
use App\Livewire\Host\Profile as HostProfile;
use App\Livewire\ManualBookingForm;
use App\Livewire\Pages\ProfileIndex;
use App\Livewire\ProgramBookingForm;
use App\Models\Accommodation;
use App\Models\County;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BeneficiaryUserProvisioner;
use App\Support\AdminUserFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProvinceAccountingCodeComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private User $hostUser;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 4,
            'room_count'       => 5,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);

        $this->hostUser = User::create([
            'name'   => 'میزبان تست',
            'mobile' => '09120000001',
        ]);
        $this->hostUser->assignRole('host');
        $this->accommodation->hosts()->attach($this->hostUser->id);

        $this->adminUser = User::create([
            'name'   => 'ادمین',
            'mobile' => '09129990000',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_program_form_auto_assigns_beneficiary_code(): void
    {
        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openBeneficiaryModal')
            ->set('newBeneficiaryName', 'رستوران حاج حسین')
            ->set('newBeneficiaryNationalId', '1234567890')
            ->set('newBeneficiaryMobile', '09121112233')
            ->call('addBeneficiaryToCatalog')
            ->assertHasNoErrors();

        $beneficiary = ProgramBeneficiary::first();
        $this->assertSame('515101', $beneficiary->beneficiary_code);
        $this->assertSame($this->accommodation->resolvedProvince()?->id, $beneficiary->province_id);
    }

    public function test_manual_booking_form_auto_assigns_beneficiary_code(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ManualBookingForm::class, [
            'accommodation' => $this->accommodation->fresh(['city.province', 'county.province']),
            'panel'         => 'admin',
        ])
            ->call('openBeneficiaryModal')
            ->set('newBeneficiaryName', 'ذینفع رزرو دستی')
            ->set('newBeneficiaryNationalId', '2234567890')
            ->set('newBeneficiaryMobile', '09122223344')
            ->call('addBeneficiaryToCatalog')
            ->assertHasNoErrors();

        $this->assertSame('515101', ProgramBeneficiary::value('beneficiary_code'));
    }

    public function test_manual_booking_verify_booker_with_beneficiary_accounting_code(): void
    {
        $beneficiary = ProgramBeneficiary::create([
            'province_id'             => $this->accommodation->resolvedProvince()?->id,
            'name'                    => 'ذینفع قابل جستجو',
            'beneficiary_code'        => '515102',
            'national_or_economic_id' => '3234567890',
            'mobile'                  => '09123334455',
        ]);
        $linked = app(BeneficiaryUserProvisioner::class)->linkBeneficiary($beneficiary);

        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city.province']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '515102')
            ->call('verifyBooker')
            ->assertHasNoErrors()
            ->assertSet('bookerVerified', true)
            ->assertSet('userId', $linked->user_id);
    }

    public function test_manual_booking_rejects_unknown_accounting_code(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city.province']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '515999')
            ->call('verifyBooker')
            ->assertHasErrors(['bookerNationalId'])
            ->assertSet('bookerVerified', false);
    }

    public function test_manual_booking_blocks_host_personnel_code_for_guest_booking(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;
        $host = User::create([
            'name'           => 'پرسنل مسدود',
            'mobile'         => '09124445566',
            'province_id'    => $provinceId,
            'personnel_code' => '515701',
        ]);
        $host->assignRole('host');

        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city.province']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '515701')
            ->call('verifyBooker')
            ->assertHasErrors(['bookerNationalId'])
            ->assertSet('bookerVerified', false);
    }

    public function test_manual_booking_rejects_invalid_identifier_length(): void
    {
        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city.province']),
                'panel'         => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 2, $this->roomType->id, $this->roomRate->id, 0, false, 0, 2)
            ->call('nextStep')
            ->set('bookerNationalId', '12345')
            ->call('verifyBooker')
            ->assertHasErrors(['bookerNationalId']);
    }

    public function test_county_based_accommodation_resolves_province_for_codes(): void
    {
        $province = Province::create(['name' => 'گیلان', 'accounting_code' => '526']);
        $city = \App\Models\City::create(['province_id' => $province->id, 'name' => 'رشت']);
        $county = County::create(['province_id' => $province->id, 'name' => 'شهرستان رشت']);
        $accommodation = Accommodation::create([
            'city_id'         => $city->id,
            'county_id'       => $county->id,
            'name'            => 'اقامتگاه شهرستان',
            'price_per_night' => 900_000,
            'capacity'        => 8,
            'rooms'           => 4,
            'is_active'       => true,
        ]);

        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $accommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'استانداری گیلان')
            ->set('newEmployerNationalId', '4234567890')
            ->set('newEmployerMobile', '09125556677')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors();

        $this->assertSame('526401', ProgramEmployer::value('employer_code'));
    }

    public function test_different_provinces_get_different_code_prefixes(): void
    {
        $tehranProvinceId = DB::table('provinces')->insertGetId([
            'name'            => 'تهران',
            'accounting_code' => '508',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $tehranCityId = DB::table('cities')->insertGetId([
            'province_id' => $tehranProvinceId,
            'name'        => 'تهران مرکزی',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $tehranAccommodation = Accommodation::create([
            'city_id'         => $tehranCityId,
            'name'            => 'اقامتگاه تهران',
            'price_per_night' => 2_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);
        $tehranAccommodation->hosts()->attach($this->hostUser->id);

        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'ارگان مازندران')
            ->set('newEmployerNationalId', '5234567890')
            ->set('newEmployerMobile', '09126667788')
            ->call('addEmployerToCatalog');

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $tehranAccommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'ارگان تهران')
            ->set('newEmployerNationalId', '6234567890')
            ->set('newEmployerMobile', '09127778899')
            ->call('addEmployerToCatalog');

        $this->assertEqualsCanonicalizing(
            ['515401', '508401'],
            ProgramEmployer::query()->orderBy('employer_code')->pluck('employer_code')->all()
        );
    }

    public function test_location_catalog_saves_unique_province_codes(): void
    {
        $province = Province::where('name', 'استان تست')->firstOrFail();

        $this->actingAs($this->adminUser);

        Livewire::test(LocationCatalogSettings::class)
            ->set("provinceAccountingCodes.{$province->id}", '519')
            ->call('saveProvinceAccountingCodes')
            ->assertHasNoErrors();

        $this->assertSame('519', $province->fresh()->accounting_code);
    }

    public function test_location_catalog_rejects_duplicate_province_codes(): void
    {
        $first = Province::first();
        $second = Province::create(['name' => 'گیلان', 'accounting_code' => '526']);

        $this->actingAs($this->adminUser);

        Livewire::test(LocationCatalogSettings::class)
            ->set("provinceAccountingCodes.{$second->id}", '515')
            ->call('saveProvinceAccountingCodes')
            ->assertHasErrors(["provinceAccountingCodes.{$second->id}"]);

        $this->assertSame('526', $second->fresh()->accounting_code);
    }

    public function test_admin_user_filter_searches_by_accounting_codes(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;

        $host = User::create([
            'name'           => 'پرسنل جستجو',
            'mobile'         => '09128880001',
            'province_id'    => $provinceId,
            'personnel_code' => '515703',
        ]);
        $host->assignRole('host');

        $employerUser = User::create(['name' => 'کاربر ارگان', 'mobile' => '09128880002']);
        $employerUser->assignRole('guest');
        ProgramEmployer::create([
            'province_id'             => $provinceId,
            'user_id'                 => $employerUser->id,
            'name'                    => 'ارگان جستجو',
            'employer_code'           => '515403',
            'national_or_economic_id' => '7234567890',
            'mobile'                  => '09128880002',
        ]);

        $beneficiaryUser = User::create(['name' => 'کاربر ذینفع', 'mobile' => '09128880003']);
        $beneficiaryUser->assignRole('guest');
        ProgramBeneficiary::create([
            'province_id'             => $provinceId,
            'user_id'                 => $beneficiaryUser->id,
            'name'                    => 'ذینفع جستجو',
            'beneficiary_code'        => '515103',
            'national_or_economic_id' => '8234567890',
            'mobile'                  => '09128880003',
        ]);

        $this->assertTrue(AdminUserFilter::make(['search' => '515703'])->apply(User::query(), false)->whereKey($host->id)->exists());
        $this->assertTrue(AdminUserFilter::make(['search' => '515403'])->apply(User::query(), false)->whereKey($employerUser->id)->exists());
        $this->assertTrue(AdminUserFilter::make(['search' => '515103'])->apply(User::query(), false)->whereKey($beneficiaryUser->id)->exists());
    }

    public function test_admin_user_show_displays_accounting_card_for_host(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;
        $host = User::create([
            'name'           => 'پرسنل نمایشی',
            'mobile'         => '09128880010',
            'province_id'    => $provinceId,
            'personnel_code' => '515704',
        ]);
        $host->assignRole('host');

        $this->actingAs($this->adminUser);

        Livewire::test(UserShow::class, ['user' => $host])
            ->assertSee('کدینگ حسابداری')
            ->assertSee('515704')
            ->assertSee('پرسنل')
            ->assertSee('کد حسابداری پرسنلی');
    }

    public function test_host_profile_displays_accounting_card(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;
        $host = User::create([
            'name'           => 'میزبان پروفایل',
            'mobile'         => '09128880011',
            'password'       => 'secret123',
            'province_id'    => $provinceId,
            'personnel_code' => '515705',
        ]);
        $host->assignRole('host');

        $this->actingAs($host);

        Livewire::test(HostProfile::class)
            ->assertSee('کد حسابداری پرسنلی')
            ->assertSee('کدینگ حسابداری')
            ->assertSee('515705');
    }

    public function test_guest_profile_displays_beneficiary_accounting_card(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;
        $guest = User::create(['name' => 'ذینفع پروفایل', 'mobile' => '09128880012']);
        $guest->assignRole('guest');

        ProgramBeneficiary::create([
            'province_id'             => $provinceId,
            'user_id'                 => $guest->id,
            'name'                    => 'ذینفع پروفایل',
            'beneficiary_code'        => '515104',
            'national_or_economic_id' => '9234567890',
            'mobile'                  => '09128880012',
        ]);

        $this->actingAs($guest);

        Livewire::test(ProfileIndex::class)
            ->assertSee('515104')
            ->assertSee('ذینفع');
    }

    public function test_program_form_previews_next_codes_in_modal(): void
    {
        ProgramEmployer::create([
            'province_id'             => $this->accommodation->resolvedProvince()?->id,
            'name'                    => 'موجود',
            'employer_code'           => '515401',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09120000002',
        ]);

        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id])
            ->call('openEmployerModal')
            ->assertSee('515402');
    }

    public function test_host_create_requires_accommodation_for_personnel_code(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(HostCreate::class)
            ->set('name', 'میزبان بدون اقامتگاه')
            ->set('mobile', '09128880020')
            ->set('hostPositionPreset', 'مدیر مجموعه')
            ->set('hostPassword', 'secret123')
            ->set('hostPassword_confirmation', 'secret123')
            ->call('save')
            ->assertHasErrors(['selectedAccommodationIds']);
    }

    public function test_user_edit_assigns_personnel_code_when_granting_first_accommodation(): void
    {
        $host = User::create([
            'name'   => 'میزبان بدون کد',
            'mobile' => '09128880021',
        ]);
        $host->assignRole('host');

        $this->actingAs($this->adminUser);

        Livewire::test(\App\Livewire\Admin\UserEdit::class, ['user' => $host])
            ->set('role', 'host')
            ->set('accommodationToAssign', $this->accommodation->id)
            ->call('assignAccommodation')
            ->assertHasNoErrors();

        $host->refresh();
        $this->assertSame('515701', $host->personnel_code);
        $this->assertSame($this->accommodation->resolvedProvince()?->id, $host->province_id);
    }

    public function test_personnel_code_uses_first_accommodation_province_not_later_ones(): void
    {
        $tehranProvinceId = DB::table('provinces')->insertGetId([
            'name'            => 'تهران',
            'accounting_code' => '508',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $tehranCityId = DB::table('cities')->insertGetId([
            'province_id' => $tehranProvinceId,
            'name'        => 'تهران',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $tehranAccommodation = Accommodation::create([
            'city_id'         => $tehranCityId,
            'name'            => 'اقامتگاه تهران',
            'price_per_night' => 2_000_000,
            'capacity'        => 10,
            'rooms'           => 5,
            'is_active'       => true,
        ]);

        $host = User::create([
            'name'   => 'میزبان دو استان',
            'mobile' => '09128880022',
        ]);
        $host->assignRole('host');

        $this->accommodation->grantHostAccess($host);
        $tehranAccommodation->grantHostAccess($host);

        $host->refresh();

        $this->assertSame('515701', $host->personnel_code);
        $this->assertSame($this->accommodation->resolvedProvince()?->id, $host->province_id);
    }

    public function test_sequential_beneficiary_codes_across_multiple_additions(): void
    {
        $this->actingAs($this->hostUser);

        $component = Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $this->accommodation->id]);

        foreach (['ذینفع ۱', 'ذینفع ۲', 'ذینفع ۳'] as $index => $name) {
            $component
                ->call('openBeneficiaryModal')
                ->set('newBeneficiaryName', $name)
                ->set('newBeneficiaryNationalId', '123456789' . $index)
                ->set('newBeneficiaryMobile', '0913000000' . $index)
                ->call('addBeneficiaryToCatalog')
                ->assertHasNoErrors();
        }

        $this->assertEquals(
            ['515101', '515102', '515103'],
            ProgramBeneficiary::query()->orderBy('id')->pluck('beneficiary_code')->all()
        );
    }

    public function test_admin_user_show_displays_employer_accounting_card(): void
    {
        $provinceId = $this->accommodation->resolvedProvince()?->id;
        $user = User::create(['name' => 'کاربر ارگان', 'mobile' => '09128880030']);
        $user->assignRole('guest');

        ProgramEmployer::create([
            'province_id'             => $provinceId,
            'user_id'                 => $user->id,
            'name'                    => 'بنیاد شهید',
            'employer_code'           => '515405',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09128880030',
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(UserShow::class, ['user' => $user->fresh(['programEmployer.province'])])
            ->assertSee('کدینگ حسابداری')
            ->assertSee('515405')
            ->assertSee('ارگان');
    }

    public function test_add_employer_fails_when_province_has_no_accounting_code_and_unknown_name(): void
    {
        $unknownProvinceId = Province::create(['name' => 'استان بدون کد تست'])->id;
        $cityId = DB::table('cities')->insertGetId([
            'province_id' => $unknownProvinceId,
            'name'        => 'شهر بدون کد',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $accommodation = Accommodation::create([
            'city_id'         => $cityId,
            'name'            => 'اقامتگاه بدون کد استان',
            'price_per_night' => 500_000,
            'capacity'        => 5,
            'rooms'           => 3,
            'is_active'       => true,
        ]);
        $accommodation->hosts()->attach($this->hostUser->id);

        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $accommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'ارگان ناموفق')
            ->set('newEmployerNationalId', '1234567890')
            ->set('newEmployerMobile', '09128880040')
            ->call('addEmployerToCatalog')
            ->assertHasErrors(['newEmployerName']);

        $this->assertDatabaseCount('program_employers', 0);
    }

    public function test_auto_resolves_province_code_from_catalog_when_missing_in_database(): void
    {
        $province = Province::create(['name' => 'گیلان']);
        $city = \App\Models\City::create(['province_id' => $province->id, 'name' => 'لاهیجان']);
        $accommodation = Accommodation::create([
            'city_id'         => $city->id,
            'name'            => 'اقامتگاه گیلان',
            'price_per_night' => 700_000,
            'capacity'        => 6,
            'rooms'           => 3,
            'is_active'       => true,
        ]);
        $accommodation->hosts()->attach($this->hostUser->id);

        $this->actingAs($this->hostUser);

        Livewire::test(ProgramBookingForm::class, ['panel' => 'host', 'accommodationId' => $accommodation->id])
            ->call('openEmployerModal')
            ->set('newEmployerName', 'ارگان گیلان')
            ->set('newEmployerNationalId', '2234567890')
            ->set('newEmployerMobile', '09128880050')
            ->call('addEmployerToCatalog')
            ->assertHasNoErrors();

        $this->assertSame('526', $province->fresh()->accounting_code);
        $this->assertSame('526401', ProgramEmployer::value('employer_code'));
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(10 + $nights)->toDateString();

        return [$checkIn, $checkOut];
    }
}
