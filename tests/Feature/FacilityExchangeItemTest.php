<?php

namespace Tests\Feature;

use App\Livewire\Host\FacilityNeededCreate;
use App\Livewire\Host\FacilityNeededIndex;
use App\Livewire\Host\FacilitySurplusCreate;
use App\Livewire\Host\FacilitySurplusEdit;
use App\Livewire\Host\FacilitySurplusIndex;
use App\Models\FacilityExchangeItem;
use App\Models\FacilityItemBrand;
use App\Models\FacilityItemCategory;
use App\Models\HostPositionTitle;
use App\Models\Province;
use App\Models\User;
use App\Services\FacilityItemCategoryCatalogService;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacilityExchangeItemTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private User $otherHost;
    private Province $province;
    private FacilityItemCategory $category;
    private FacilityItemBrand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->province = Province::query()->create(['name' => 'تهران']);
        $otherProvince = Province::query()->create(['name' => 'اصفهان']);

        $this->host = User::create([
            'name'        => 'میزبان امکان',
            'mobile'      => '09120000090',
            'province_id' => $this->province->id,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $this->host->assignRole('host');

        $this->otherHost = User::create([
            'name'        => 'میزبان دیگر',
            'mobile'      => '09120000091',
            'province_id' => $otherProvince->id,
            'host_panel_permissions' => HostPermissions::fullAccessGrants(),
        ]);
        $this->otherHost->assignRole('host');

        app(FacilityItemCategoryCatalogService::class)->ensureSeeded();
        $this->category = FacilityItemCategory::query()->where('name', 'تاسیسات')->firstOrFail();
        $this->brand = FacilityItemBrand::query()->create([
            'name'       => 'برند تست',
            'sort_order' => 0,
        ]);

        Storage::fake('public');
    }

    public function test_host_permissions_catalog_includes_facility_management(): void
    {
        $catalog = HostPermissions::catalog();

        $this->assertArrayHasKey('facility-management', $catalog);
        $this->assertArrayHasKey('facility-surplus.list', $catalog['facility-management']['pages']);
        $this->assertArrayHasKey('facility-needed.create', $catalog['facility-management']['pages']);
    }

    public function test_restricted_host_cannot_access_facility_pages(): void
    {
        $grants = ['bookings.list' => ['read']];

        HostPositionTitle::query()->updateOrCreate(
            ['label' => 'میزبان'],
            [
                'is_system'              => true,
                'sort_order'             => 0,
                'host_panel_permissions' => $grants,
            ],
        );

        $restricted = User::create([
            'name'                   => 'میزبان محدود',
            'mobile'                 => '09120000092',
            'host_position_title'    => 'میزبان',
            'host_panel_permissions' => HostPositionTitles::grantsForPositionLabel('میزبان'),
        ]);
        $restricted->assignRole('host');

        $this->actingAs($restricted)
            ->get(route('host.facility.surplus.index'))
            ->assertRedirect(route('host.bookings.index'));
    }

    public function test_surplus_index_lists_items_from_all_hosts(): void
    {
        FacilityExchangeItem::query()->create([
            'user_id'        => $this->host->id,
            'type'           => FacilityExchangeItem::TYPE_SURPLUS,
            'name'           => 'گالن آب',
            'brand_id'       => $this->brand->id,
            'category_id'    => $this->category->id,
            'unit_volume'    => 'دو گالن ده لیتری',
            'quantity'       => 2,
            'province_id'    => $this->province->id,
            'contact_phone'  => '09121111111',
        ]);

        FacilityExchangeItem::query()->create([
            'user_id'        => $this->otherHost->id,
            'type'           => FacilityExchangeItem::TYPE_SURPLUS,
            'name'           => 'پمپ آب',
            'brand_id'       => $this->brand->id,
            'category_id'    => $this->category->id,
            'unit_volume'    => 'یک دستگاه',
            'quantity'       => 1,
            'province_id'    => $this->province->id,
            'contact_phone'  => '09122222222',
        ]);

        $this->actingAs($this->host)
            ->get(route('host.facility.surplus.index'))
            ->assertOk()
            ->assertSee('گالن آب')
            ->assertSee('پمپ آب');
    }

    public function test_host_can_create_surplus_item_with_image(): void
    {
        $image = UploadedFile::fake()->image('item.jpg', 800, 600);

        Livewire::actingAs($this->host)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'گالن آب')
            ->set('brandId', $this->brand->id)
            ->set('categoryId', $this->category->id)
            ->set('unitVolume', 'دو گالن ده لیتری')
            ->set('quantity', 2)
            ->set('provinceId', $this->province->id)
            ->set('contactPhone', '09123456789')
            ->set('description', 'سالم و تمیز')
            ->set('images', [$image])
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect(route('host.facility.surplus.index'));

        $item = FacilityExchangeItem::query()->where('user_id', $this->host->id)->first();

        $this->assertNotNull($item);
        $this->assertSame('گالن آب', $item->name);
        $this->assertSame(FacilityExchangeItem::TYPE_SURPLUS, $item->type);
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_surplus_create_defaults_province_from_host(): void
    {
        Livewire::actingAs($this->host)
            ->test(FacilitySurplusCreate::class)
            ->assertSet('provinceId', $this->province->id)
            ->assertSet('contactPhone', '09120000090');
    }

    public function test_host_can_create_needed_item_without_image(): void
    {
        Livewire::actingAs($this->host)
            ->test(FacilityNeededCreate::class)
            ->set('name', 'پمپ آب')
            ->set('brandId', $this->brand->id)
            ->set('categoryId', $this->category->id)
            ->set('unitVolume', 'یک دستگاه')
            ->set('quantity', 1)
            ->set('provinceId', $this->province->id)
            ->set('contactPhone', '09123456789')
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect(route('host.facility.needed.index'));

        $item = FacilityExchangeItem::query()
            ->where('type', FacilityExchangeItem::TYPE_NEEDED)
            ->first();

        $this->assertNotNull($item);
        $this->assertNull($item->image_path);
    }

    public function test_host_can_add_category_and_brand_from_form(): void
    {
        Livewire::actingAs($this->host)
            ->test(FacilitySurplusCreate::class)
            ->call('toggleAddCategory')
            ->set('newCategoryName', 'دسته ویژه')
            ->call('addCategory')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('facility_item_categories', ['name' => 'دسته ویژه']);

        Livewire::actingAs($this->host)
            ->test(FacilitySurplusCreate::class)
            ->call('toggleAddBrand')
            ->set('newBrandName', 'برند ویژه')
            ->call('addBrand')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('facility_item_brands', ['name' => 'برند ویژه']);
    }

    public function test_host_can_edit_own_surplus_item(): void
    {
        $item = FacilityExchangeItem::query()->create([
            'user_id'        => $this->host->id,
            'type'           => FacilityExchangeItem::TYPE_SURPLUS,
            'name'           => 'گالن آب',
            'brand_id'       => $this->brand->id,
            'category_id'    => $this->category->id,
            'unit_volume'    => 'دو گالن',
            'quantity'       => 2,
            'province_id'    => $this->province->id,
            'contact_phone'  => '09121111111',
        ]);

        Livewire::actingAs($this->host)
            ->test(FacilitySurplusEdit::class, ['item' => $item])
            ->set('name', 'گالن آب به‌روز')
            ->set('quantity', 3)
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('host.facility.surplus.index'));

        $item->refresh();
        $this->assertSame('گالن آب به‌روز', $item->name);
        $this->assertSame(3, $item->quantity);
    }

    public function test_host_cannot_edit_other_hosts_surplus_item(): void
    {
        $item = FacilityExchangeItem::query()->create([
            'user_id'        => $this->otherHost->id,
            'type'           => FacilityExchangeItem::TYPE_SURPLUS,
            'name'           => 'کالای دیگران',
            'brand_id'       => $this->brand->id,
            'category_id'    => $this->category->id,
            'unit_volume'    => 'یک عدد',
            'quantity'       => 1,
            'province_id'    => $this->province->id,
            'contact_phone'  => '09123333333',
        ]);

        $this->actingAs($this->host)
            ->get(route('host.facility.surplus.edit', $item))
            ->assertForbidden();
    }

    public function test_host_can_delete_own_surplus_item_from_index(): void
    {
        $item = FacilityExchangeItem::query()->create([
            'user_id'        => $this->host->id,
            'type'           => FacilityExchangeItem::TYPE_SURPLUS,
            'name'           => 'برای حذف',
            'brand_id'       => $this->brand->id,
            'category_id'    => $this->category->id,
            'unit_volume'    => 'یک عدد',
            'quantity'       => 1,
            'province_id'    => $this->province->id,
            'contact_phone'  => '09124444444',
            'image_path'     => 'facility-exchange/test.webp',
        ]);

        Storage::disk('public')->put('facility-exchange/test.webp', 'fake');

        Livewire::actingAs($this->host)
            ->test(FacilitySurplusIndex::class)
            ->call('destroy', $item->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('facility_exchange_items', ['id' => $item->id]);
        Storage::disk('public')->assertMissing('facility-exchange/test.webp');
    }

    public function test_surplus_create_accepts_missing_image(): void
    {
        Livewire::actingAs($this->host)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'بدون عکس')
            ->set('brandId', $this->brand->id)
            ->set('categoryId', $this->category->id)
            ->set('unitVolume', 'یک عدد')
            ->set('quantity', 1)
            ->set('provinceId', $this->province->id)
            ->set('contactPhone', '09123456789')
            ->call('store')
            ->assertHasNoErrors()
            ->assertRedirect(route('host.facility.surplus.index'));

        $item = FacilityExchangeItem::query()->first();
        $this->assertNotNull($item);
        $this->assertNull($item->image_path);
    }

    public function test_invalid_expiry_date_is_rejected(): void
    {
        $image = UploadedFile::fake()->image('item.jpg');

        Livewire::actingAs($this->host)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'گالن آب')
            ->set('brandId', $this->brand->id)
            ->set('categoryId', $this->category->id)
            ->set('unitVolume', 'دو گالن')
            ->set('quantity', 1)
            ->set('provinceId', $this->province->id)
            ->set('contactPhone', '09123456789')
            ->set('expiryDateJalali', 'invalid-date')
            ->set('images', [$image])
            ->call('store')
            ->assertHasErrors(['expiryDateJalali']);
    }

    public function test_backfill_grants_facility_management_for_existing_modules(): void
    {
        $grants = HostPermissions::moduleFullAccessGrants('accommodations');
        $backfilled = HostPermissions::backfillFacilityManagementGrants($grants);

        $this->assertArrayHasKey('facility-surplus.list', $backfilled);
        $this->assertArrayHasKey('facility-needed.list', $backfilled);
    }
}
