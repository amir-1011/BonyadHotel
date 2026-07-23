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

    public function test_admin_global_settings_remove_service_from_all_accommodations(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000992',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $secondAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);

        Livewire::test(VeteranPolicySettings::class)
            ->call('removeService', 'pool')
            ->assertHasNoErrors()
            ->assertCount('services', 5);

        foreach ([$this->accommodation, $secondAccommodation] as $accommodation) {
            $this->assertDatabaseMissing('service_catalogs', [
                'accommodation_id' => $accommodation->id,
                'key'              => 'pool',
            ]);
        }

        $this->assertArrayNotHasKey('pool', Livewire::test(VeteranPolicySettings::class)->get('services'));
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

    public function test_admin_can_remove_single_veteran_group_for_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000997',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $group = VeteranGroup::forAccommodation($this->accommodation->id)
            ->where('key', 'freed_prisoner_dependents')
            ->firstOrFail();

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->call('removeVeteranGroup', $group->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('veteran_groups', ['id' => $group->id]);
        $this->assertSame(
            6,
            VeteranGroup::forAccommodation($this->accommodation->id)->count(),
        );
    }

    public function test_admin_can_remove_service_and_livewire_services_list_stays_in_sync(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000991',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $pool = $this->veteranCatalog($this->accommodation, 'pool');

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->call('removeService', $pool->id)
            ->assertHasNoErrors()
            ->assertCount('services', 5)
            ->assertSet('services.pool', null);

        $this->assertDatabaseMissing('service_catalogs', ['id' => $pool->id]);
    }

    public function test_admin_can_clear_all_veteran_groups_for_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000996',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->call('clearAllVeteranGroups')
            ->assertHasNoErrors();

        $this->assertSame(0, VeteranGroup::forAccommodation($this->accommodation->id)->count());
        $this->assertGreaterThan(0, ServiceCatalog::forAccommodation($this->accommodation->id)->count());

        $this->accommodation->refresh();
        $this->assertFalse($this->accommodation->veteran_policy_auto_seed);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->assertSet('groups', []);
    }

    public function test_admin_can_clear_all_services_for_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000995',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->call('clearAllServices')
            ->assertHasNoErrors();

        $this->assertSame(0, ServiceCatalog::forAccommodation($this->accommodation->id)->count());
        $this->assertGreaterThan(0, VeteranGroup::forAccommodation($this->accommodation->id)->count());

        $this->accommodation->refresh();
        $this->assertFalse($this->accommodation->veteran_policy_auto_seed);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->assertSet('services', []);
    }

    public function test_cleared_policy_does_not_auto_reseed_on_page_reload(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000994',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->call('clearAllVeteranGroups')
            ->call('clearAllServices')
            ->assertHasNoErrors();

        $this->accommodation->refresh();
        $this->assertFalse($this->accommodation->veteran_policy_auto_seed);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->assertSet('groups', [])
            ->assertSet('services', []);

        $this->assertSame(0, VeteranGroup::forAccommodation($this->accommodation->id)->count());
        $this->assertSame(0, ServiceCatalog::forAccommodation($this->accommodation->id)->count());
    }

    public function test_admin_can_restore_default_veteran_policy_for_accommodation(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000993',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $this->accommodation])
            ->call('clearAllVeteranGroups')
            ->call('clearAllServices')
            ->call('restoreDefaultVeteranPolicy')
            ->assertHasNoErrors();

        $this->accommodation->refresh();
        $this->assertTrue($this->accommodation->veteran_policy_auto_seed);
        $this->assertGreaterThan(0, VeteranGroup::forAccommodation($this->accommodation->id)->count());
        $this->assertGreaterThan(0, ServiceCatalog::forAccommodation($this->accommodation->id)->count());
    }

    public function test_restore_accommodation_policy_copies_global_admin_settings(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000990',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $secondAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);

        Livewire::test(VeteranPolicySettings::class)
            ->set('groups.0.accommodation_discount', 82)
            ->call('saveGroups')
            ->assertHasNoErrors();

        VeteranGroup::query()
            ->where('accommodation_id', $secondAccommodation->id)
            ->where('key', 'veteran_70_spouses')
            ->update(['accommodation_discount' => 11]);

        Livewire::test(AccommodationVeteranPolicySettings::class, ['accommodation' => $secondAccommodation])
            ->call('restoreDefaultVeteranPolicy')
            ->assertHasNoErrors();

        $restored = VeteranGroup::forAccommodation($secondAccommodation->id)
            ->where('key', 'veteran_70_spouses')
            ->firstOrFail();

        $this->assertSame(82, $restored->accommodation_discount);
    }

    public function test_admin_global_settings_sync_group_discount_only_to_filtered_accommodations(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000989',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $secondAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);
        $thirdAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه سوم']);

        Livewire::test(VeteranPolicySettings::class)
            ->set('dashboardAccommodationAllSelected', false)
            ->set('selectedAccommodationIds', [$this->accommodation->id, $secondAccommodation->id])
            ->set('groups.0.accommodation_discount', 66)
            ->call('saveGroups')
            ->assertHasNoErrors();

        foreach ([$this->accommodation, $secondAccommodation] as $accommodation) {
            $group = VeteranGroup::forAccommodation($accommodation->id)
                ->where('key', 'veteran_70_spouses')
                ->firstOrFail();
            $this->assertSame(66, $group->accommodation_discount);
        }

        $untouched = VeteranGroup::forAccommodation($thirdAccommodation->id)
            ->where('key', 'veteran_70_spouses')
            ->firstOrFail();
        $this->assertSame(70, $untouched->accommodation_discount);
    }

    public function test_admin_can_add_custom_group_only_to_filtered_accommodations(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000988',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $secondAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);

        Livewire::test(VeteranPolicySettings::class)
            ->set('dashboardAccommodationAllSelected', false)
            ->set('selectedAccommodationIds', [$secondAccommodation->id])
            ->set('newGroupLabel', 'گروه اختصاصی دوم')
            ->set('newGroupAccommodationDiscount', 44)
            ->call('addCustomGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('veteran_groups', [
            'accommodation_id' => $secondAccommodation->id,
            'label'            => 'گروه اختصاصی دوم',
        ]);

        $this->assertDatabaseMissing('veteran_groups', [
            'accommodation_id' => $this->accommodation->id,
            'label'            => 'گروه اختصاصی دوم',
        ]);
    }

    public function test_admin_global_settings_remove_service_only_from_filtered_accommodations(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000987',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $secondAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);

        Livewire::test(VeteranPolicySettings::class)
            ->set('dashboardAccommodationAllSelected', false)
            ->set('selectedAccommodationIds', [$this->accommodation->id])
            ->call('removeService', 'pool')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('service_catalogs', [
            'accommodation_id' => $this->accommodation->id,
            'key'              => 'pool',
        ]);

        $this->assertDatabaseHas('service_catalogs', [
            'accommodation_id' => $secondAccommodation->id,
            'key'              => 'pool',
        ]);
    }

    public function test_veteran_policy_page_exposes_accommodation_badges_for_shared_policy_keys(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000986',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $secondAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);

        $component = Livewire::test(VeteranPolicySettings::class);

        $groupMap = $component->viewData('groupAccommodationsByKey');
        $this->assertArrayHasKey('veteran_70_spouses', $groupMap);
        $this->assertGreaterThanOrEqual(2, count($groupMap['veteran_70_spouses']));

        $names = collect($groupMap['veteran_70_spouses'])->pluck('name')->all();
        $this->assertContains($this->accommodation->name, $names);
        $this->assertContains($secondAccommodation->name, $names);
    }

    public function test_veteran_policy_page_exposes_variant_accommodation_badges_by_service(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09100000985',
        ]);
        $admin->assignRole('super_admin');
        $this->actingAs($admin);

        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $variant = \App\Models\ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'pool_test_variant',
            'name'               => 'استخر تست',
            'price'              => 100_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $map = Livewire::test(VeteranPolicySettings::class)->viewData('variantAccommodationsByServiceKey');

        $this->assertArrayHasKey('pool', $map);
        $this->assertArrayHasKey($variant->key, $map['pool']);
        $this->assertNotEmpty($map['pool'][$variant->key]);
    }
}
