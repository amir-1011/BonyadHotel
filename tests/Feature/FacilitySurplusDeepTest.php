<?php

namespace Tests\Feature;

use App\Livewire\Host\FacilitySurplusCreate;
use App\Livewire\Host\FacilitySurplusEdit;
use App\Livewire\Host\FacilitySurplusIndex;
use App\Models\FacilityExchangeItem;
use App\Models\FacilityItemCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithFacilityExchange;
use Tests\TestCase;

class FacilitySurplusDeepTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFacilityExchange;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->setupFacilityExchangeContext();
    }

    public function test_surplus_index_excludes_needed_items(): void
    {
        $this->makeSurplusItem(['name' => 'فقط مازاد']);
        $this->makeNeededItem(['name' => 'فقط مورد نیاز']);

        $this->actingAs($this->facilityHost)
            ->get(route('host.facility.surplus.index'))
            ->assertOk()
            ->assertSee('فقط مازاد')
            ->assertDontSee('فقط مورد نیاز');
    }

    public function test_surplus_index_filters_by_search_name_brand_and_description(): void
    {
        $otherBrand = \App\Models\FacilityItemBrand::query()->create([
            'name'       => 'برند خاص',
            'sort_order' => 1,
        ]);

        $this->makeSurplusItem(['name' => 'پمپ آب', 'description' => null]);
        $this->makeSurplusItem([
            'name'     => 'کالای دیگر',
            'brand_id' => $otherBrand->id,
        ]);
        $this->makeSurplusItem([
            'name'        => 'کالای سوم',
            'description' => 'توضیح ویژه جستجو',
        ]);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusIndex::class)
            ->set('search', 'پمپ')
            ->assertSee('پمپ آب')
            ->assertDontSee('کالای سوم');

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusIndex::class)
            ->set('search', 'برند خاص')
            ->assertSee('کالای دیگر')
            ->assertDontSee('پمپ آب');

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusIndex::class)
            ->set('search', 'ویژه')
            ->assertSee('کالای سوم')
            ->assertDontSee('پمپ آب');
    }

    public function test_surplus_index_filters_by_category_and_province(): void
    {
        $consumables = FacilityItemCategory::query()->where('name', 'مواد مصرفی')->firstOrFail();

        $this->makeSurplusItem([
            'name'        => 'در تهران',
            'category_id' => $this->facilityCategory->id,
            'province_id' => $this->facilityProvince->id,
        ]);
        $this->makeSurplusItem([
            'name'        => 'در اصفهان',
            'category_id' => $consumables->id,
            'province_id' => $this->facilityOtherProvince->id,
        ]);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusIndex::class)
            ->set('provinceId', $this->facilityOtherProvince->id)
            ->assertSee('در اصفهان')
            ->assertDontSee('در تهران');

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusIndex::class)
            ->set('categoryId', $consumables->id)
            ->assertSee('در اصفهان')
            ->assertDontSee('در تهران');
    }

    public function test_surplus_index_mine_only_shows_only_own_items(): void
    {
        $this->makeSurplusItem(['name' => 'آگهی من', 'user_id' => $this->facilityHost->id]);
        $this->makeSurplusItem(['name' => 'آگهی دیگران', 'user_id' => $this->facilityOtherHost->id]);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusIndex::class)
            ->set('mineOnly', '1')
            ->assertSee('آگهی من')
            ->assertDontSee('آگهی دیگران');
    }

    public function test_surplus_create_accepts_landline_phone_and_persian_expiry(): void
    {
        $image = $this->fakeFacilityImage();

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'گالن')
            ->set('brandId', $this->facilityBrand->id)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', 'یک گالن')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '02112345678')
            ->set('expiryDateJalali', '۱۴۰۴/۰۶/۱۵')
            ->set('images', [$image])
            ->call('store')
            ->assertHasNoErrors();

        $item = FacilityExchangeItem::query()->latest('id')->first();
        $this->assertSame('02112345678', $item->contact_phone);
        $this->assertNotNull($item->expiry_date);
    }

    public function test_surplus_edit_loads_jalali_expiry_from_record(): void
    {
        $item = $this->makeSurplusItem(['expiry_date' => '2025-12-01']);
        $expectedJalali = \Morilog\Jalali\Jalalian::fromDateTime('2025-12-01')->format('Y/m/d');

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusEdit::class, ['item' => $item])
            ->assertSet('name', $item->name)
            ->assertSet('expiryDateJalali', $expectedJalali);
    }

    public function test_surplus_edit_can_clear_expiry_date(): void
    {
        $item = $this->makeSurplusItem(['expiry_date' => '2025-12-01']);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusEdit::class, ['item' => $item])
            ->set('expiryDateJalali', '')
            ->call('update')
            ->assertHasNoErrors();

        $this->assertNull($item->fresh()->expiry_date);
    }

    public function test_surplus_edit_rejects_needed_item_on_surplus_route(): void
    {
        $needed = $this->makeNeededItem();

        $this->actingAs($this->facilityHost)
            ->get(route('host.facility.surplus.edit', $needed))
            ->assertNotFound();
    }

    public function test_destroy_other_hosts_surplus_item_does_not_delete_record(): void
    {
        $otherItem = $this->makeSurplusItem(['user_id' => $this->facilityOtherHost->id]);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusIndex::class)
            ->call('destroy', $otherItem->id)
            ->assertForbidden();

        $this->assertDatabaseHas('facility_exchange_items', ['id' => $otherItem->id]);
    }

    public function test_host_without_delete_permission_cannot_destroy_own_item(): void
    {
        $host = $this->hostWithGrants([
            'facility-surplus.list' => ['read'],
            'facility-surplus.edit' => ['read', 'edit'],
        ], '09120000110');

        $item = $this->makeSurplusItem(['user_id' => $host->id]);

        Livewire::actingAs($host)
            ->test(FacilitySurplusIndex::class)
            ->call('destroy', $item->id)
            ->assertForbidden();

        $this->assertDatabaseHas('facility_exchange_items', ['id' => $item->id]);
    }

    public function test_host_without_write_permission_cannot_store_via_livewire(): void
    {
        $host = $this->hostWithGrants([
            'facility-surplus.list' => ['read'],
        ], '09120000111');

        Livewire::actingAs($host)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'تلاش')
            ->set('brandId', $this->facilityBrand->id)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', 'یک')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->set('images', [$this->fakeFacilityImage()])
            ->call('store')
            ->assertForbidden();

        $this->assertDatabaseCount('facility_exchange_items', 0);
    }

    public function test_add_province_from_form_sets_province_id(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->call('toggleAddProvince')
            ->set('newProvinceName', 'گیلان')
            ->call('addProvince')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('provinces', ['name' => 'گیلان']);
    }

    public function test_model_image_url_is_null_without_path(): void
    {
        $item = $this->makeSurplusItem(['image_path' => null]);

        $this->assertNull($item->imageUrl());
    }

    public function test_surplus_edit_can_replace_image(): void
    {
        $item = $this->makeSurplusItem([
            'image_path' => 'facility-exchange/old.webp',
            'image_paths' => ['facility-exchange/old.webp'],
        ]);
        Storage::disk('public')->put('facility-exchange/old.webp', 'old');

        $newImage = $this->fakeFacilityImage('replacement.jpg');

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusEdit::class, ['item' => $item])
            ->set('keptImagePaths', [])
            ->set('images', [$newImage])
            ->call('update')
            ->assertHasNoErrors();

        $item->refresh();
        Storage::disk('public')->assertMissing('facility-exchange/old.webp');
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_surplus_edit_can_remove_image_without_replacement(): void
    {
        $item = $this->makeSurplusItem([
            'image_path' => 'facility-exchange/remove-only.webp',
            'image_paths' => ['facility-exchange/remove-only.webp'],
        ]);
        Storage::disk('public')->put('facility-exchange/remove-only.webp', 'data');

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusEdit::class, ['item' => $item])
            ->set('keptImagePaths', [])
            ->call('update')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertNull($item->image_path);
        Storage::disk('public')->assertMissing('facility-exchange/remove-only.webp');
    }

    public function test_model_type_helpers_and_labels(): void
    {
        $surplus = $this->makeSurplusItem();
        $needed = $this->makeNeededItem();

        $this->assertTrue($surplus->isSurplus());
        $this->assertFalse($surplus->isNeeded());
        $this->assertSame('اقلام مازاد', FacilityExchangeItem::typeLabel(FacilityExchangeItem::TYPE_SURPLUS));
        $this->assertSame('اقلام مورد نیاز', FacilityExchangeItem::typeLabel(FacilityExchangeItem::TYPE_NEEDED));
        $this->assertTrue($needed->isNeeded());
    }
}
