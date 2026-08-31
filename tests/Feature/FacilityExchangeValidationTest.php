<?php

namespace Tests\Feature;

use App\Livewire\Host\FacilitySurplusCreate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithFacilityExchange;
use Tests\TestCase;

class FacilityExchangeValidationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFacilityExchange;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->setupFacilityExchangeContext();
    }

    public function test_surplus_create_rejects_missing_required_fields(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('contactPhone', '')
            ->call('store')
            ->assertHasErrors([
                'name',
                'categoryId',
                'contactPhone',
            ]);
    }

    public function test_surplus_create_rejects_invalid_mobile_phone(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'کالا')
            ->set('brandId', $this->facilityBrand->id)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', 'یک')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '12345')
            ->set('images', [$this->fakeFacilityImage()])
            ->call('store')
            ->assertHasErrors(['contactPhone']);
    }

    public function test_surplus_create_defaults_quantity_when_empty(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'کالا')
            ->set('categoryId', $this->facilityCategory->id)
            ->set('quantity', 0)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->set('images', [$this->fakeFacilityImage()])
            ->call('store')
            ->assertHasNoErrors();

        $this->assertSame(1, \App\Models\FacilityExchangeItem::query()->first()->quantity);
    }

    public function test_surplus_create_accepts_missing_brand_and_unit_volume(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'کالا بدون برند')
            ->set('brandId', 0)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', '')
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->set('images', [$this->fakeFacilityImage()])
            ->call('store')
            ->assertHasNoErrors();

        $item = \App\Models\FacilityExchangeItem::query()->first();
        $this->assertNull($item->brand_id);
        $this->assertSame('', $item->unit_volume);
    }

    public function test_surplus_create_rejects_invalid_brand_and_category_ids(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'کالا')
            ->set('brandId', 99999)
            ->set('categoryId', 99999)
            ->set('unitVolume', 'یک')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->set('images', [$this->fakeFacilityImage()])
            ->call('store')
            ->assertHasErrors(['brandId', 'categoryId']);
    }

    public function test_surplus_create_rejects_non_image_upload(): void
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'کالا')
            ->set('brandId', $this->facilityBrand->id)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', 'یک')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->set('images', [$file])
            ->call('store')
            ->assertHasErrors(['images.0']);
    }

    public function test_surplus_create_rejects_duplicate_province_name(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->call('toggleAddProvince')
            ->set('newProvinceName', 'تهران')
            ->call('addProvince')
            ->assertHasErrors(['newProvinceName']);
    }

    public function test_description_is_trimmed_and_null_when_empty(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilitySurplusCreate::class)
            ->set('name', 'کالا')
            ->set('brandId', $this->facilityBrand->id)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', 'یک')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->set('description', '   ')
            ->set('images', [$this->fakeFacilityImage()])
            ->call('store')
            ->assertHasNoErrors();

        $this->assertNull(
            \App\Models\FacilityExchangeItem::query()->first()->description,
        );
    }

    public function test_host_without_province_defaults_province_to_zero(): void
    {
        $host = $this->hostWithGrants(
            \App\Support\HostPermissions::fullAccessGrants(),
            '09120000130',
        );
        $host->update(['province_id' => null]);

        Livewire::actingAs($host)
            ->test(FacilitySurplusCreate::class)
            ->assertSet('provinceId', 0);
    }
}
