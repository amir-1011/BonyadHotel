<?php

namespace Tests\Feature;

use App\Livewire\BookingServicesEditor;
use App\Livewire\Host\BookingShow;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingService;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Services\BookingPriceChangePreviewService;
use App\Services\RefundPolicyService;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualServiceBookingShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_preview_defaults_to_full_amount_for_manual_service_sale(): void
    {
        $this->seed(VeteranPolicySeeder::class);
        $accommodation = $this->createTestAccommodation();
        $pool = $this->veteranCatalog($accommodation, 'pool');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'ticket',
            'name'               => 'بلیط',
            'price'              => 5_000_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09123334455', 'national_id' => '4440123456']);
        $today = now()->format('Y-m-d');

        $booking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $accommodation->id,
            'booking_source'       => 'manual_service',
            'check_in'             => $today,
            'check_out'            => $today,
            'nights'               => 0,
            'guests'               => 1,
            'base_price'           => 5_250_000,
            'services_subtotal'    => 5_000_000,
            'discount_amount'      => 0,
            'total_price'          => 5_250_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'SRVSHOW01',
        ]);

        BookingService::create([
            'booking_id'                 => $booking->id,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                       => 'استخر — بلیط',
            'unit_price'                 => 5_000_000,
            'quantity'                   => 1,
            'total'                      => 5_000_000,
            'discount_amount'            => 0,
        ]);

        $preview = app(RefundPolicyService::class)->previewForBooking($booking);
        $this->assertSame(100, $preview['percentage']);
        $this->assertSame(5_250_000, $preview['amount']);
    }

    public function test_host_booking_show_hides_room_cards_for_manual_service_sale(): void
    {
        $this->seed(VeteranPolicySeeder::class);
        $accommodation = $this->createTestAccommodation();
        $host = User::create(['name' => 'میزبان', 'mobile' => '09120000999']);
        $accommodation->hosts()->attach($host->id);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121112222']);
        $today = now()->format('Y-m-d');

        $booking = Booking::create([
            'user_id'          => $guest->id,
            'accommodation_id' => $accommodation->id,
            'booking_source'   => 'manual_service',
            'check_in'         => $today,
            'check_out'        => $today,
            'nights'           => 0,
            'guests'           => 1,
            'base_price'       => 500_000,
            'services_subtotal'=> 500_000,
            'total_price'      => 525_000,
            'status'           => 'confirmed',
            'tracking_code'    => 'SRVSHOW02',
        ]);

        Livewire::actingAs($host)
            ->test(BookingShow::class, ['booking' => $booking])
            ->assertSee('فروش خدمات')
            ->assertSee('فهرست خدمات')
            ->assertDontSee('اتاق‌ها')
            ->assertDontSee('مهمانان')
            ->assertDontSee('مدیریت خدمات و فرم رزرو')
            ->assertSee('مدیریت خدمات فروش');
    }

    public function test_adding_service_on_manual_service_sale_reports_positive_price_preview(): void
    {
        $this->seed(VeteranPolicySeeder::class);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09000003333']);
        $admin->assignRole('super_admin');

        $accommodation = $this->createTestAccommodation();
        $pool = $this->veteranCatalog($accommodation, 'pool');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'ticket',
            'name'               => 'بلیط',
            'price'              => 1_000_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09124445566', 'national_id' => '4440123456']);
        $today = now()->format('Y-m-d');

        $booking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $accommodation->id,
            'booking_source'       => 'manual_service',
            'check_in'             => $today,
            'check_out'            => $today,
            'nights'               => 0,
            'guests'               => 1,
            'base_price'           => 1_050_000,
            'services_subtotal'    => 1_000_000,
            'total_price'          => 1_050_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'SRVADD01',
            'payment_method'       => 'card_terminal',
        ]);

        BookingGuestDetail::create([
            'booking_id'  => $booking->id,
            'sort_order'  => 0,
            'full_name'   => 'مهمان',
            'national_id' => '4440123456',
            'mobile'      => '09124445566',
            'relation'    => 'self',
        ]);

        BookingService::create([
            'booking_id'                 => $booking->id,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                       => 'استخر — بلیط',
            'unit_price'                 => 1_000_000,
            'quantity'                   => 1,
            'total'                      => 1_000_000,
            'guest_sort_order'           => 0,
        ]);

        $this->assertTrue(
            app(BookingPriceChangePreviewService::class)->bookingSupportsAutoRepricing($booking->fresh())
        );

        Livewire::actingAs($admin)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $booking->id,
                'panel'          => 'admin',
                'guestSortOrder' => 0,
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'نوشیدنی')
            ->set('newServicePrice', 200_000)
            ->set('newServiceQty', 1)
            ->call('previewBookingPriceChange', 'addServiceLine', [])
            ->assertReturned(fn (array $result) => $result['error'] === false
                && $result['affects_price'] === true
                && $result['auto_delta'] > 0);
    }
}
