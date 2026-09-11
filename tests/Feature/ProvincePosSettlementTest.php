<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProvincePosSettlementIndex;
use App\Models\ProvincePosSettlement;
use App\Services\PcPosShareAllocator;
use App\Support\IranIban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Tests\TestCase;

class ProvincePosSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $this->admin = User::create(['name' => 'ادمین تسهیم', 'mobile' => '09120000011']);
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_can_save_province_split_accounts(): void
    {
        $accommodation = $this->createTestAccommodation();
        $province = $accommodation->fresh(['city.province'])->resolvedProvince();
        $this->assertNotNull($province);

        $this->actingAs($this->admin)
            ->get(route('admin.pos-settlements.index'))
            ->assertOk();

        Livewire::actingAs($this->admin)
            ->test(ProvincePosSettlementIndex::class)
            ->call('selectProvince', $province->id)
            ->set('serviceFeeIban', 'IR360550010200200165000001')
            ->set('remainderAccounts', [
                ['label' => 'حساب استان', 'iban' => 'IR090550010200200165000002', 'percentage' => '70'],
                ['label' => 'حساب بنیاد', 'iban' => 'IR170550010200200165000003', 'percentage' => '30'],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $settlement = ProvincePosSettlement::query()->where('province_id', $province->id)->first();
        $this->assertNotNull($settlement);
        $this->assertSame('IR360550010200200165000001', $settlement->service_fee_iban);
        $this->assertCount(2, $settlement->accounts);
        $this->assertEquals(70.0, (float) $settlement->accounts->first()->percentage);
        $this->assertEquals(30.0, (float) $settlement->accounts->last()->percentage);
    }

    public function test_admin_rejects_remainder_percentages_that_do_not_sum_to_one_hundred(): void
    {
        $accommodation = $this->createTestAccommodation();
        $province = $accommodation->fresh(['city.province'])->resolvedProvince();

        Livewire::actingAs($this->admin)
            ->test(ProvincePosSettlementIndex::class)
            ->call('selectProvince', $province->id)
            ->set('serviceFeeIban', 'IR360550010200200165000001')
            ->set('remainderAccounts', [
                ['label' => 'حساب اول', 'iban' => 'IR090550010200200165000002', 'percentage' => '60'],
                ['label' => 'حساب دوم', 'iban' => 'IR170550010200200165000003', 'percentage' => '30'],
            ])
            ->call('save')
            ->assertHasErrors(['remainderAccounts']);
    }

    public function test_allocator_sends_service_fee_then_splits_remainder(): void
    {
        $accommodation = $this->createTestAccommodation();
        $settlement = $this->seedSettlement($accommodation);

        $shares = app(PcPosShareAllocator::class)->allocate($settlement, 1_050_000, 50_000);

        $this->assertCount(3, $shares);
        $this->assertSame('50000', $shares[0]['amount']);
        $this->assertSame('service_fee', $shares[0]['kind']);
        $this->assertSame('IR360550010200200165000001', $shares[0]['iban']);
        $this->assertSame('700000', $shares[1]['amount']);
        $this->assertSame('300000', $shares[2]['amount']);
        $this->assertSame(1_050_000, (int) $shares[0]['amount'] + (int) $shares[1]['amount'] + (int) $shares[2]['amount']);
        $this->assertTrue(IranIban::isValid($shares[1]['iban']));
        $this->assertNotSame($shares[1]['trackingId'], $shares[2]['trackingId']);
    }

    public function test_agent_payload_omits_labels_and_sums_to_amount(): void
    {
        $accommodation = $this->createTestAccommodation();
        $settlement = $this->seedSettlement($accommodation);
        $allocator = app(PcPosShareAllocator::class);
        $shares = $allocator->allocate($settlement, 20000, 0);
        $payload = $allocator->toAgentShares($shares);

        $this->assertCount(2, $payload);
        $this->assertSame(['amount', 'iban', 'trackingId'], array_keys($payload[0]));
        $this->assertSame(20000, (int) $payload[0]['amount'] + (int) $payload[1]['amount']);
    }

    private function seedSettlement($accommodation): ProvincePosSettlement
    {
        $province = $accommodation->fresh(['city.province', 'county.province'])->resolvedProvince();
        $settlement = ProvincePosSettlement::create([
            'province_id' => $province->id,
            'service_fee_label' => 'حق سرویس',
            'service_fee_iban' => 'IR360550010200200165000001',
            'is_active' => true,
        ]);
        $settlement->accounts()->create([
            'label' => 'حساب اول',
            'iban' => 'IR090550010200200165000002',
            'percentage' => 70,
            'sort_order' => 0,
        ]);
        $settlement->accounts()->create([
            'label' => 'حساب دوم',
            'iban' => 'IR170550010200200165000003',
            'percentage' => 30,
            'sort_order' => 1,
        ]);

        return $settlement->fresh('accounts');
    }
}
