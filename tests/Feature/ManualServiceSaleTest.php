<?php

namespace Tests\Feature;

use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\PlatformCommissionEntry;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Services\BookingPricingService;
use App\Services\ManualBookingService;
use App\Services\PlatformCommissionService;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualServiceSaleTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;

    private User $adminUser;

    private ServiceCatalog $pool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation([
            'name'            => 'اقامتگاه فروش خدمات',
            'price_per_night' => 1_000_000,
            'capacity'        => 4,
            'rooms'           => 2,
            'is_active'       => true,
        ]);

        $this->pool = $this->veteranCatalog($this->accommodation, 'pool');

        $this->adminUser = User::create([
            'name'   => 'ادمین خدمات',
            'mobile' => '09000000222',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_percentage_commission_five_percent_capped_at_fifty_thousand(): void
    {
        $commission = app(PlatformCommissionService::class);

        $this->assertSame(250_000, $commission->calculatePercentageCappedCommission(5_000_000));
        $this->assertSame(500_000, $commission->calculatePercentageCappedCommission(80_000_000));
        $this->assertSame(0, $commission->calculatePercentageCappedCommission(0));
    }

    public function test_manual_service_sale_pricing_includes_commission_on_total(): void
    {
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $this->pool->id,
            'key'                => 'ticket',
            'name'               => 'بلیط',
            'price'              => 5_000_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $pricing = app(BookingPricingService::class)->calculateManualServiceSale([
            'veteran_types'  => [],
            'services'       => [[
                'service_catalog_id'         => $this->pool->id,
                'service_catalog_variant_id' => $variant->id,
                'name'                       => 'استخر — بلیط',
                'unit_price'                 => 5_000_000,
                'quantity'                   => 1,
            ]],
            'accommodation'  => $this->accommodation,
            'national_id'    => '4440123456',
        ]);

        $pricing = app(PlatformCommissionService::class)->overlayPricing($pricing, [
            'booking_source' => 'manual_service',
        ]);

        $this->assertSame(5_000_000, $pricing['services_subtotal']);
        $this->assertSame(250_000, $pricing['platform_commission_amount']);
        $this->assertSame(5_250_000, $pricing['total_price']);
    }

    public function test_manual_booking_service_creates_commission_wallet_entry(): void
    {
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $this->pool->id,
            'key'                => 'direct',
            'name'               => 'ورود',
            'price'              => 1_000_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $guest = User::create([
            'name'        => 'مهمان مستقیم',
            'mobile'      => '09120001111',
            'national_id' => '1234567890',
        ]);

        $booking = app(ManualBookingService::class)->create($this->accommodation, [
            'service_sale_only'    => true,
            'booker_national_id'   => '1234567890',
            'user_id'              => $guest->id,
            'guest_contact_name'   => 'مهمان مستقیم',
            'guest_contact_mobile' => '09120001111',
            'payment_method'       => 'card_terminal',
            'services'             => [[
                'service_catalog_id'         => $this->pool->id,
                'service_catalog_variant_id' => $variant->id,
                'name'                       => 'استخر — ورود',
                'unit_price'                 => 1_000_000,
                'quantity'                   => 1,
                'excluded_from_veteran_quota' => true,
                'manual_discount_percentage' => 0,
                'manual_discount_reason'     => 'تست',
            ]],
            'guest_details'        => [[
                'full_name'                      => 'مهمان مستقیم',
                'national_id'                    => '1234567890',
                'mobile'                         => '09120001111',
                'relation'                       => 'self',
                'excluded_from_veteran_discount' => false,
                'services'                       => [],
            ]],
        ], $this->adminUser);

        $this->assertSame('manual_service', $booking->booking_source);

        $entry = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'service_sale')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(50_000, $entry->commission_amount);
    }

    public function test_inline_service_wizard_three_steps_persist_catalog(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
                'formMode'      => 'service_sale',
            ])
            ->call('startInlineWizardNew')
            ->set('inlineParentServiceName', 'رستوران ویزارد')
            ->call('inlineWizardAdvanceFromParent')
            ->assertSet('inlineWizardStep', 2);

        $serviceId = (int) $component->get('inlineEditingServiceId');
        $this->assertGreaterThan(0, $serviceId);

        $component
            ->set("newVariantDrafts.{$serviceId}.name", 'زرشک‌پلو')
            ->set("newVariantDrafts.{$serviceId}.price", 5_000_000)
            ->call('addInlineServiceVariant', $serviceId)
            ->call('inlineWizardAdvanceFromVariants')
            ->assertSet('inlineWizardStep', 3)
            ->set('discountMatrix.veteran_70_spouses.' . $serviceId . '.discount_percentage', 50)
            ->call('finishInlineWizardDiscounts')
            ->assertSet('showInlineServiceCatalogModal', false);

        $service = ServiceCatalog::find($serviceId);
        $this->assertNotNull($service);
        $this->assertSame('رستوران ویزارد', $service->name);
        $this->assertSame(1, $service->variants()->count());
        $this->assertSame(5_000_000, (int) $service->variants()->first()->price);

        $discount = \App\Models\VeteranGroupServiceDiscount::query()
            ->where('service_catalog_id', $serviceId)
            ->whereHas('veteranGroup', fn ($q) => $q->where('key', 'veteran_70_spouses'))
            ->first();
        $this->assertNotNull($discount);
        $this->assertSame(50, (int) $discount->discount_percentage);
    }

    public function test_parent_service_activates_when_it_has_active_variants(): void
    {
        $this->pool->update(['is_active' => false]);
        ServiceCatalogVariant::create([
            'service_catalog_id' => $this->pool->id,
            'key'                => 'ticket',
            'name'               => 'بلیط',
            'price'              => 1_000_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
                'formMode'      => 'service_sale',
            ])
            ->call('openInlineServiceCatalogModal');

        $this->pool->refresh();
        $this->assertTrue($this->pool->is_active);

        $card = collect($component->get('inlineCatalogCards'))->firstWhere('id', $this->pool->id);
        $this->assertNotNull($card);
        $this->assertTrue($card['is_active']);
    }

    public function test_inline_service_wizard_edit_existing_service(): void
    {
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $this->pool->id,
            'key'                => 'meal',
            'name'               => 'ناهار',
            'price'              => 2_000_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
                'formMode'      => 'service_sale',
            ])
            ->call('openInlineServiceCatalogModal', $this->pool->id)
            ->assertSet('inlineCatalogScreen', 'wizard')
            ->assertSet('inlineWizardStep', 1)
            ->assertSet('inlineParentServiceName', $this->pool->name)
            ->call('inlineWizardAdvanceFromParent')
            ->assertSet('inlineWizardStep', 2)
            ->set("inlineWizardServices.{$this->pool->key}.variants.0.name", 'ناهار ویژه')
            ->call('inlineWizardAdvanceFromVariants')
            ->assertSet('inlineWizardStep', 3)
            ->call('finishInlineWizardDiscounts')
            ->assertSet('showInlineServiceCatalogModal', false);

        $variant->refresh();
        $this->assertSame('ناهار ویژه', $variant->name);
    }

    public function test_manual_service_sale_livewire_registers_booking_and_commission_wallet(): void
    {
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $this->pool->id,
            'key'                => 'session',
            'name'               => 'ورود',
            'price'              => 500_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, [
                'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel'         => 'admin',
                'formMode'      => 'service_sale',
            ])
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->assertSet('bookerVerified', true)
            ->set('guestContactName', 'مهمان خدمات')
            ->set('guestContactMobile', '09121112233')
            ->call('nextStep')
            ->assertSet('step', 2)
            ->assertDontSee('تخفیف دستی اقامت')
            ->call('addGuestService', 0)
            ->set('guestDetails.0.services.0.service_catalog_id', (string) $this->pool->id)
            ->set('guestDetails.0.services.0.service_catalog_variant_id', (string) $variant->id)
            ->set('guestDetails.0.services.0.quantity', 1)
            ->set('guestDetails.0.services.0.excluded_from_veteran_quota', true)
            ->set('guestDetails.0.services.0.manual_discount_percentage', '0')
            ->set('guestDetails.0.services.0.manual_discount_reason', 'تست فروش خدمات')
            ->call('confirmGuestService', 0, 0)
            ->call('nextStep')
            ->assertSet('step', 3)
            ->set('paymentMethod', 'card_terminal')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 4);

        $booking = Booking::query()->where('booking_source', 'manual_service')->latest('id')->first();
        $this->assertNotNull($booking);
        $this->assertSame(0, $booking->nights);
        $this->assertCount(1, $booking->services);

        $servicesTotal = (int) $booking->services->sum('total');
        $expectedCommission = app(PlatformCommissionService::class)
            ->calculatePercentageCappedCommission($servicesTotal);
        $this->assertSame($servicesTotal + $expectedCommission, $booking->total_price);

        $entry = PlatformCommissionEntry::query()
            ->where('booking_id', $booking->id)
            ->where('category_key', 'service_sale')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame($expectedCommission, $entry->commission_amount);
    }
}
