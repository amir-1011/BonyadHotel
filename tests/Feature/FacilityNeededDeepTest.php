<?php

namespace Tests\Feature;

use App\Livewire\Host\FacilityNeededCreate;
use App\Livewire\Host\FacilityNeededEdit;
use App\Livewire\Host\FacilityNeededIndex;
use App\Models\FacilityExchangeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithFacilityExchange;
use Tests\TestCase;

class FacilityNeededDeepTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithFacilityExchange;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupFacilityExchangeContext();
    }

    public function test_needed_index_excludes_surplus_items(): void
    {
        $this->makeSurplusItem(['name' => 'کالای-مازاد-اختصاصی-تست']);
        $this->makeNeededItem(['name' => 'کالای-مورد-نیاز-اختصاصی-تست']);

        $this->actingAs($this->facilityHost)
            ->get(route('host.facility.needed.index'))
            ->assertOk()
            ->assertSee('کالای-مورد-نیاز-اختصاصی-تست')
            ->assertDontSee('کالای-مازاد-اختصاصی-تست');
    }

    public function test_host_can_edit_own_needed_item(): void
    {
        $item = $this->makeNeededItem(['name' => 'پمپ', 'quantity' => 2]);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilityNeededEdit::class, ['item' => $item])
            ->set('name', 'پمپ آب')
            ->set('quantity', 5)
            ->call('update')
            ->assertHasNoErrors()
            ->assertRedirect(route('host.facility.needed.index'));

        $item->refresh();
        $this->assertSame('پمپ آب', $item->name);
        $this->assertSame(5, $item->quantity);
    }

    public function test_host_cannot_edit_other_hosts_needed_item(): void
    {
        $item = $this->makeNeededItem(['user_id' => $this->facilityOtherHost->id]);

        $this->actingAs($this->facilityHost)
            ->get(route('host.facility.needed.edit', $item))
            ->assertForbidden();
    }

    public function test_needed_edit_rejects_surplus_item_on_needed_route(): void
    {
        $surplus = $this->makeSurplusItem();

        $this->actingAs($this->facilityHost)
            ->get(route('host.facility.needed.edit', $surplus))
            ->assertNotFound();
    }

    public function test_host_can_delete_own_needed_item(): void
    {
        $item = $this->makeNeededItem();

        Livewire::actingAs($this->facilityHost)
            ->test(FacilityNeededIndex::class)
            ->call('destroy', $item->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('facility_exchange_items', ['id' => $item->id]);
    }

    public function test_destroy_other_hosts_needed_item_leaves_record(): void
    {
        $otherItem = $this->makeNeededItem(['user_id' => $this->facilityOtherHost->id]);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilityNeededIndex::class)
            ->call('destroy', $otherItem->id)
            ->assertForbidden();

        $this->assertDatabaseHas('facility_exchange_items', ['id' => $otherItem->id]);
    }

    public function test_needed_create_does_not_require_or_accept_image_field(): void
    {
        Livewire::actingAs($this->facilityHost)
            ->test(FacilityNeededCreate::class)
            ->set('name', 'پمپ')
            ->set('brandId', $this->facilityBrand->id)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', 'یک دستگاه')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->call('store')
            ->assertHasNoErrors();

        $item = FacilityExchangeItem::query()->where('type', FacilityExchangeItem::TYPE_NEEDED)->first();
        $this->assertNotNull($item);
        $this->assertNull($item->image_path);
    }

    public function test_needed_index_filters_with_mine_only(): void
    {
        $this->makeNeededItem(['name' => 'درخواست من', 'user_id' => $this->facilityHost->id]);
        $this->makeNeededItem(['name' => 'درخواست دیگران', 'user_id' => $this->facilityOtherHost->id]);

        Livewire::actingAs($this->facilityHost)
            ->test(FacilityNeededIndex::class)
            ->set('mineOnly', '1')
            ->assertSee('درخواست من')
            ->assertDontSee('درخواست دیگران');
    }

    public function test_host_without_needed_write_cannot_store(): void
    {
        $host = $this->hostWithGrants([
            'facility-needed.list' => ['read'],
        ], '09120000120');

        Livewire::actingAs($host)
            ->test(FacilityNeededCreate::class)
            ->set('name', 'تلاش')
            ->set('brandId', $this->facilityBrand->id)
            ->set('categoryId', $this->facilityCategory->id)
            ->set('unitVolume', 'یک')
            ->set('quantity', 1)
            ->set('provinceId', $this->facilityProvince->id)
            ->set('contactPhone', '09123456789')
            ->call('store')
            ->assertForbidden();
    }
}
