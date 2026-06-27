<?php

namespace Tests\Feature;

use App\Livewire\Admin\AccommodationVeteranPolicySettings;
use App\Livewire\Admin\VeteranPolicySettings;
use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Models\VeteranGroup;
use App\Services\BookingPricingService;
use App\Services\NationalIdVerificationService;
use App\Services\VeteranPolicyProvisioner;
use App\Services\VeteranPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VeteranPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accommodation = $this->createTestAccommodation();
    }

    public function test_seeder_creates_seven_veteran_groups(): void
    {
        $this->assertDatabaseCount('veteran_groups', 7);
        $this->assertDatabaseCount('service_catalogs', 6);
        $this->assertDatabaseCount('veteran_group_service_discounts', 42);
    }

    public function test_veteran_70_group_has_70_percent_accommodation_discount(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);

        $this->assertSame(70, $policy->accommodationDiscount('veteran_70_spouses'));
        $this->assertSame('جانبازان ۷۰ درصد و همسران', $policy->groupByKey('veteran_70_spouses')?->label);
    }

    public function test_legacy_veteran_keys_are_normalized(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);

        $this->assertSame('veteran_70_spouses', $policy->normalizeKey('veteran_70_plus'));
        $this->assertSame(70, $policy->accommodationDiscount('veteran_70_plus'));
    }

    public function test_conference_hall_discount_is_40_percent_for_all_groups(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $conference = $this->veteranCatalog($this->accommodation, 'conference_hall');

        foreach (VeteranGroup::forAccommodation($this->accommodation->id)->get() as $group) {
            $rule = $policy->serviceDiscountRule($group->key, $conference->id);
            $this->assertSame(40, $rule['discount_percentage'], "Failed for group {$group->key}");
        }
    }

    public function test_pool_is_free_eligible_for_70_percent_veterans(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $pool = $this->veteranCatalog($this->accommodation, 'pool');

        $rule70 = $policy->serviceDiscountRule('veteran_70_spouses', $pool->id);
        $rule50 = $policy->serviceDiscountRule('veteran_50_69_dependents', $pool->id);

        $this->assertTrue($rule70['free_sessions_eligible']);
        $this->assertSame(3, $rule70['weekly_free_sessions']);
        $this->assertFalse($rule50['free_sessions_eligible']);
        $this->assertSame(65, $rule50['discount_percentage']);
    }

    public function test_pricing_applies_separate_accommodation_and_service_discounts(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);
        $pricing = app(BookingPricingService::class);
        $pool = $this->veteranCatalog($this->accommodation, 'pool');

        $result = $pricing->calculate([
            'check_in'     => now()->addDays(7)->format('Y-m-d'),
            'check_out'    => now()->addDays(9)->format('Y-m-d'),
            'guests'       => 2,
            'extra_guests' => 0,
            'veteran_type' => 'veteran_70_spouses',
            'services'     => $policy->enrichServicesWithDiscounts('veteran_70_spouses', [[
                'service_catalog_id' => $pool->id,
                'name'               => 'استخر',
                'unit_price'         => 200_000,
                'quantity'           => 2,
            ]]),
            'accommodation' => $this->accommodation,
            'room_type'     => null,
            'room_rate'     => null,
        ]);

        $this->assertSame(2, $result['nights']);
        $this->assertSame(4_000_000, $result['room_subtotal']);
        $this->assertSame(400_000, $result['services_subtotal']);
        $this->assertSame(70, $result['accommodation_discount_percentage']);
        $this->assertSame(2_800_000, $result['discount_amount'] - $result['services_discount_amount']);
        $this->assertSame(400_000, $result['services_discount_amount']);
        $this->assertSame(1_200_000, $result['total_price']);
    }

    public function test_pricing_applies_veteran_discount_only_within_quota_nights(): void
    {
        $pricing = app(BookingPricingService::class);

        $checkIn = now()->addDays(7)->format('Y-m-d');
        $checkOut = now()->addDays(11)->format('Y-m-d');

        $result = $pricing->calculate([
            'check_in'      => $checkIn,
            'check_out'     => $checkOut,
            'guests'          => 1,
            'extra_guests'    => 0,
            'veteran_type'    => 'veteran_70_spouses',
            'services'        => [],
            'accommodation'   => $this->accommodation,
            'room_type'       => null,
            'room_rate'       => null,
        ]);

        $this->assertSame(4, $result['nights']);
        $this->assertSame(3, $result['veteran_discount_nights']);
        $this->assertSame(4_000_000, $result['room_subtotal']);
        $this->assertSame(2_100_000, $result['veteran_accommodation_discount_amount']);
        $this->assertSame(1_900_000, $result['total_price']);
    }

    public function test_usage_limit_allows_exceeding_period_cap_with_partial_discount(): void
    {
        $policy = $this->veteranPolicyFor($this->accommodation);

        $check = $policy->checkAccommodationUsage(
            'veteran_70_spouses',
            2,
            4,
        );

        $this->assertTrue($check['allowed']);
        $this->assertSame(3, $check['discounted_nights']);
        $this->assertStringContainsString('3 شب', $check['message'] ?? '');
        $this->assertStringContainsString('نرخ عادی', $check['message'] ?? '');
    }

    public function test_national_id_verification_uses_new_group_keys(): void
    {
        $service = app(NationalIdVerificationService::class);

        $result = $service->verify('4441234567');
        $this->assertTrue($result['valid']);
        $this->assertSame('veteran_70_spouses', $result['veteran_type']);
        $this->assertSame(70, $result['discount']);
    }

    public function test_pool_and_gym_can_have_different_weekly_free_session_quotas(): void
    {
        $group = VeteranGroup::forAccommodation($this->accommodation->id)
            ->where('key', 'veteran_70_spouses')
            ->firstOrFail();
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $gym = $this->veteranCatalog($this->accommodation, 'gym');

        $poolDiscount = $group->serviceDiscounts()->where('service_catalog_id', $pool->id)->firstOrFail();
        $gymDiscount = $group->serviceDiscounts()->where('service_catalog_id', $gym->id)->firstOrFail();

        $poolDiscount->update(['free_sessions_eligible' => true, 'weekly_free_sessions' => 4]);
        $gymDiscount->update(['free_sessions_eligible' => true, 'weekly_free_sessions' => 2]);

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);

        $poolRule = $policy->serviceDiscountRule('veteran_70_spouses', $pool->id);
        $gymRule = $policy->serviceDiscountRule('veteran_70_spouses', $gym->id);

        $this->assertSame(4, $poolRule['weekly_free_sessions']);
        $this->assertSame(2, $gymRule['weekly_free_sessions']);
    }

    public function test_admin_veteran_policy_page_requires_auth(): void
    {
        $this->get('/admin/veteran-policy')->assertRedirect();
    }

    public function test_admin_can_add_custom_veteran_group_with_service_discount_rows(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000999',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $serviceCount = ServiceCatalog::forAccommodation($this->accommodation->id)->count();

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->set('newGroupLabel', 'گروه آزمایشی')
            ->set('newGroupAccommodationDiscount', 55)
            ->call('addCustomGroup')
            ->assertHasNoErrors();

        $group = VeteranGroup::forAccommodation($this->accommodation->id)
            ->where('label', 'گروه آزمایشی')
            ->first();
        $this->assertNotNull($group);
        $this->assertStringStartsWith('custom_group_', $group->key);
        $this->assertSame(55, $group->accommodation_discount);
        $this->assertSame($serviceCount, $group->serviceDiscounts()->count());

        $policy = $this->veteranPolicyFor($this->accommodation);
        $policy->clearCache($this->accommodation->id);
        $options = $policy->optionsForUi();
        $this->assertArrayHasKey($group->key, $options);
        $this->assertSame('گروه آزمایشی', $options[$group->key]['label']);
    }

    public function test_admin_global_settings_sync_group_discount_to_all_accommodations(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000998',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $secondAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);

        Livewire::test(VeteranPolicySettings::class)
            ->set('groups.0.accommodation_discount', 75)
            ->call('saveGroups')
            ->assertHasNoErrors();

        foreach ([$this->accommodation, $secondAccommodation] as $accommodation) {
            $group = VeteranGroup::forAccommodation($accommodation->id)
                ->where('key', 'veteran_70_spouses')
                ->firstOrFail();
            $this->assertSame(75, $group->accommodation_discount);
        }
    }

    public function test_veteran_label_resolves_without_accommodation_context(): void
    {
        $user = User::create([
            'name'         => 'جانباز تست',
            'mobile'       => '09100000111',
            'national_id'  => '4441234567',
            'veteran_type' => 'veteran_70_spouses',
        ]);

        $this->assertSame('جانبازان ۷۰ درصد و همسران', $user->veteranLabel());
        $this->assertSame('جانبازان ۷۰ درصد و همسران', \App\Support\VeteranGroups::label('veteran_70_plus'));
    }
}
