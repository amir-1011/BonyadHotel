<?php

namespace Tests\Concerns;

use App\Models\FacilityExchangeItem;
use App\Models\FacilityItemBrand;
use App\Models\FacilityItemCategory;
use App\Models\Province;
use App\Models\User;
use App\Services\FacilityItemCategoryCatalogService;
use App\Support\HostPermissions;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

trait InteractsWithFacilityExchange
{
    protected User $facilityHost;

    protected User $facilityOtherHost;

    protected Province $facilityProvince;

    protected Province $facilityOtherProvince;

    protected FacilityItemCategory $facilityCategory;

    protected FacilityItemBrand $facilityBrand;

    protected function setupFacilityExchangeContext(): void
    {
        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->facilityProvince = Province::query()->create(['name' => 'تهران']);
        $this->facilityOtherProvince = Province::query()->create(['name' => 'اصفهان']);

        $this->facilityHost = User::create([
            'name'                   => 'میزبان امکان',
            'mobile'                 => '09120000090',
            'province_id'            => $this->facilityProvince->id,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $this->facilityHost->assignRole('host');

        $this->facilityOtherHost = User::create([
            'name'                   => 'میزبان دیگر',
            'mobile'                 => '09120000091',
            'province_id'            => $this->facilityOtherProvince->id,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $this->facilityOtherHost->assignRole('host');

        app(FacilityItemCategoryCatalogService::class)->ensureSeeded();
        $this->facilityCategory = FacilityItemCategory::query()->where('name', 'تاسیسات')->firstOrFail();
        $this->facilityBrand = FacilityItemBrand::query()->create([
            'name'       => 'برند تست',
            'sort_order' => 0,
        ]);
    }

    protected function hostWithGrants(array $grants, string $mobile = '09129999999'): User
    {
        $host = User::create([
            'name'                   => 'میزبان سفارشی',
            'mobile'                 => $mobile,
            'province_id'            => $this->facilityProvince->id,
            'host_panel_permissions' => $grants,
        ]);
        $host->assignRole('host');

        return $host;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSurplusItem(array $overrides = []): FacilityExchangeItem
    {
        return FacilityExchangeItem::query()->create(array_merge([
            'user_id'        => $this->facilityHost->id,
            'type'           => FacilityExchangeItem::TYPE_SURPLUS,
            'name'           => 'کالای مازاد',
            'brand_id'       => $this->facilityBrand->id,
            'category_id'    => $this->facilityCategory->id,
            'unit_volume'    => 'یک واحد',
            'quantity'       => 1,
            'province_id'    => $this->facilityProvince->id,
            'contact_phone'  => '09121111111',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeNeededItem(array $overrides = []): FacilityExchangeItem
    {
        return FacilityExchangeItem::query()->create(array_merge([
            'user_id'        => $this->facilityHost->id,
            'type'           => FacilityExchangeItem::TYPE_NEEDED,
            'name'           => 'کالای مورد نیاز',
            'brand_id'       => $this->facilityBrand->id,
            'category_id'    => $this->facilityCategory->id,
            'unit_volume'    => 'یک واحد',
            'quantity'       => 1,
            'province_id'    => $this->facilityProvince->id,
            'contact_phone'  => '09121111111',
        ], $overrides));
    }

    protected function fakeFacilityImage(string $name = 'item.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 640, 480);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validSurplusPayload(): array
    {
        return [
            'name'           => 'گالن آب',
            'brand_id'       => $this->facilityBrand->id,
            'category_id'    => $this->facilityCategory->id,
            'unit_volume'    => 'دو گالن ده لیتری',
            'quantity'       => 2,
            'province_id'    => $this->facilityProvince->id,
            'contact_phone'  => '09123456789',
            'description'    => 'توضیح تست',
        ];
    }
}
