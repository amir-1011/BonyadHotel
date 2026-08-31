<?php

namespace Tests\Feature;

use App\Livewire\BookingServicesEditor;
use App\Livewire\RoomStatusBoard;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\ServiceCatalogVariant;
use App\Models\User;
use App\Services\ManualBookingService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomStatusBoardBookingServicesTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $physicalRoom;
    private User $host;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق دو تخته',
            'capacity'         => 2,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
        $this->physicalRoom = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۱',
            'sort_order'   => 1,
            'is_active'    => true,
        ]);

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09120000999']);
        $this->host->assignRole('host');
        $this->accommodation->hosts()->attach($this->host->id);

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121112222', 'national_id' => '1234567890']);
        $guest->assignRole('guest');

        $checkIn = now()->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $this->booking = Booking::create([
            'user_id'              => $guest->id,
            'accommodation_id'     => $this->accommodation->id,
            'booking_source'       => 'manual',
            'check_in'             => $checkIn,
            'check_out'            => $checkOut,
            'nights'               => 2,
            'guests'               => 2,
            'base_price'           => 2_000_000,
            'services_subtotal'    => 450_000,
            'discount_amount'      => 0,
            'total_price'          => 2_450_000,
            'status'               => 'confirmed',
            'tracking_code'        => 'TESTBOARD01',
            'guest_contact_name'   => 'مهمان',
            'guest_contact_mobile' => '09121112222',
        ]);

        BookingRoom::create([
            'booking_id'   => $this->booking->id,
            'room_type_id' => $this->roomType->id,
            'room_rate_id' => $this->roomRate->id,
            'room_id'      => $this->physicalRoom->id,
            'adults'       => 2,
            'guests'       => 2,
            'sort_order'   => 0,
        ]);

        $bookingRoom = BookingRoom::where('booking_id', $this->booking->id)->first();

        BookingGuestDetail::create([
            'booking_id'      => $this->booking->id,
            'booking_room_id' => $bookingRoom->id,
            'sort_order'      => 0,
            'full_name'       => 'مهمان اول',
            'relation'        => 'رزرو‌کننده',
        ]);
        BookingGuestDetail::create([
            'booking_id'      => $this->booking->id,
            'booking_room_id' => $bookingRoom->id,
            'sort_order'      => 1,
            'full_name'       => 'مهمان دوم',
            'relation'        => 'همسر',
        ]);

        BookingService::create([
            'booking_id'       => $this->booking->id,
            'guest_sort_order' => 0,
            'name'             => 'صبحانه',
            'unit_price'       => 100_000,
            'quantity'         => 3,
            'total'            => 300_000,
            'sort_order'       => 0,
        ]);
        BookingService::create([
            'booking_id'       => $this->booking->id,
            'guest_sort_order' => 1,
            'name'             => 'استخر',
            'unit_price'       => 150_000,
            'quantity'         => 1,
            'total'            => 150_000,
            'sort_order'       => 1,
        ]);
    }

    public function test_room_status_board_shows_guests_and_per_guest_services(): void
    {
        Livewire::actingAs($this->host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('selectRoom', $this->accommodation->id, $this->physicalRoom->id)
            ->assertSet('servicesBookingId', $this->booking->id)
            ->assertSee('مهمانان و خدمات این اتاق')
            ->assertSee('مهمان اول')
            ->assertSee('مهمان دوم')
            ->assertSee('صبحانه')
            ->assertSee('استخر');
    }

    public function test_booking_services_editor_adjusts_quantity_and_recalculates(): void
    {
        $service = $this->booking->services()->where('guest_sort_order', 0)->first();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 0,
            ])
            ->call('adjustServiceQuantity', $service->id, 1)
            ->assertSet("editableServices.{$service->id}.quantity", 4)
            ->call('applyServiceQuantity', $service->id)
            ->assertDispatched('booking-services-updated');

        $service->refresh();
        $this->assertSame(4, $service->quantity);
        $this->assertSame(400_000, $service->total);
        $this->assertSame(550_000, $this->booking->fresh()->services_subtotal);
    }

    public function test_booking_services_editor_adds_custom_service_for_specific_guest(): void
    {
        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 1,
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'لباسشویی')
            ->set('newServicePrice', 50_000)
            ->set('newServiceQty', 2)
            ->call('addServiceLine')
            ->assertDispatched('booking-services-updated');

        $booking = $this->booking->fresh();
        $this->assertCount(3, $booking->services);
        $laundry = $booking->services()->where('name', 'لباسشویی')->first();
        $this->assertNotNull($laundry);
        $this->assertSame(1, $laundry->guest_sort_order);
        $this->assertSame(2, $laundry->quantity);
        $this->assertSame(100_000, $laundry->total);
    }

    public function test_booking_services_editor_removes_guest_service_without_affecting_other_guest(): void
    {
        $guestOneService = $this->booking->services()->where('guest_sort_order', 0)->first();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 0,
            ])
            ->call('removeServiceLine', $guestOneService->id);

        $booking = $this->booking->fresh();
        $this->assertCount(1, $booking->services);
        $this->assertSame('استخر', $booking->services->first()->name);
        $this->assertSame(150_000, $booking->services_subtotal);
    }

    public function test_editor_apply_button_saves_single_service_manual_edits(): void
    {
        $service = $this->booking->services()->where('guest_sort_order', 0)->first();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 0,
            ])
            ->set("editableServices.{$service->id}.name", 'صبحانه ویژه')
            ->set("editableServices.{$service->id}.unit_price", 120_000)
            ->set("editableServices.{$service->id}.quantity", 2)
            ->call('applyServiceLineEdits', $service->id)
            ->assertDispatched('booking-services-updated');

        $service->refresh();
        $this->assertSame('صبحانه ویژه', $service->name);
        $this->assertSame(120_000, $service->unit_price);
        $this->assertSame(2, $service->quantity);
        $this->assertSame(240_000, $service->total);
    }

    public function test_unauthorized_host_cannot_edit_booking_services(): void
    {
        $otherHost = User::create(['name' => 'میزبان دیگر', 'mobile' => '09123334444']);
        $otherHost->assignRole('host');

        Livewire::actingAs($otherHost)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $this->booking->id,
                'panel'     => 'host',
            ])
            ->assertForbidden();
    }

    public function test_excluded_service_keeps_manual_discount_after_quantity_adjust(): void
    {
        [, , $excluded] = $this->seedVeteranBookingWithMixedServices();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 0,
            ])
            ->call('adjustServiceQuantity', $excluded->id, 1)
            ->call('applyServiceQuantity', $excluded->id)
            ->assertDispatched('booking-services-updated');

        $excluded->refresh();
        $this->assertSame(2, $excluded->quantity);
        $this->assertTrue($excluded->excluded_from_veteran_quota);
        $this->assertSame(25, $excluded->manual_discount_percentage);
        $this->assertSame(100_000, $excluded->discount_amount);
        $this->assertSame(300_000, $excluded->total);
    }

    public function test_editor_toggle_excluded_from_quota_recalculates_pricing(): void
    {
        [, $quotaService] = $this->seedVeteranBookingWithMixedServices();
        $this->assertFalse($quotaService->excluded_from_veteran_quota);
        $this->assertSame(0, $quotaService->total);

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 0,
            ])
            ->set("editableServices.{$quotaService->id}.excluded_from_veteran_quota", true)
            ->call('runDirectPriceChange', 'applyServiceQuotaSettings', [
                'serviceId'    => $quotaService->id,
                'changedField' => 'excluded_from_veteran_quota',
            ])
            ->assertDispatched('booking-services-updated');

        $quotaService->refresh();
        $this->assertTrue($quotaService->excluded_from_veteran_quota);
        $this->assertSame(200_000, $quotaService->total);
        $this->assertSame(0, $quotaService->discount_amount);
    }

    public function test_editor_adds_service_excluded_from_quota_with_manual_discount(): void
    {
        $this->seedVeteranBookingWithMixedServices();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 1,
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'لباسشویی')
            ->set('newServicePrice', 100_000)
            ->set('newServiceQty', 1)
            ->set('newExcludedFromVeteranQuota', true)
            ->set('newManualDiscountPercentage', '20')
            ->set('newManualDiscountReason', 'توافق مدیر')
            ->call('addServiceLine')
            ->assertDispatched('booking-services-updated');

        $laundry = $this->booking->fresh()->services()->where('name', 'لباسشویی')->first();
        $this->assertNotNull($laundry);
        $this->assertTrue($laundry->excluded_from_veteran_quota);
        $this->assertSame(20, $laundry->manual_discount_percentage);
        $this->assertSame('توافق مدیر', $laundry->manual_discount_reason);
        $this->assertSame(20_000, $laundry->discount_amount);
        $this->assertSame(80_000, $laundry->total);
    }

    public function test_editor_requires_manual_discount_reason_when_percentage_set(): void
    {
        $this->seedVeteranBookingWithMixedServices();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 1,
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'خدمت بدون دلیل')
            ->set('newServicePrice', 50_000)
            ->set('newExcludedFromVeteranQuota', true)
            ->set('newManualDiscountPercentage', '15')
            ->call('addServiceLine')
            ->assertHasErrors(['newManualDiscountReason']);

        $this->assertNull($this->booking->fresh()->services()->where('name', 'خدمت بدون دلیل')->first());
    }

    public function test_editor_shows_per_service_quota_badges_not_generic_message_for_all_excluded(): void
    {
        $this->seedVeteranBookingWithAllExcludedServices();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 0,
            ])
            ->assertSee('خارج از سهمیه ایثارگری')
            ->assertDontSee('تخفیف/سهمیه خدمات علامت‌گذاری‌شده')
            ->assertDontSee('تخفیف خدمات بر اساس گروه');
    }

    public function test_editor_shows_mixed_quota_summary_when_some_services_use_veteran_quota(): void
    {
        $this->seedVeteranBookingWithMixedServices();

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId'      => $this->booking->id,
                'panel'          => 'host',
                'guestSortOrder' => 0,
            ])
            ->assertSee('سهمیه مهمان اصلی')
            ->assertSee('خارج از سهمیه ایثارگری')
            ->assertSee('خدمت از سهمیه/تخفیف گروه');
    }

    public function test_room_status_board_shows_per_guest_quota_controls(): void
    {
        $this->seedVeteranBookingWithMixedServices();

        Livewire::actingAs($this->host)
            ->test(RoomStatusBoard::class, ['panel' => 'host'])
            ->call('selectRoom', $this->accommodation->id, $this->physicalRoom->id)
            ->assertSee('هزینه این خدمت از سهمیه ایثارگری مهمان اصلی کسر نشود')
            ->assertSee('خارج از سهمیه ایثارگری')
            ->assertSee('سهمیه مهمان اصلی');
    }

    /**
     * @return array{0:Booking,1:BookingService,2:BookingService}
     */
    private function seedVeteranBookingWithMixedServices(): array
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'pool_board_test',
            'name'               => 'استخر تست',
            'price'              => 200_000,
            'sort_order'         => 1,
            'is_active'          => true,
        ]);

        $this->booking->update([
            'veteran_type_applied' => 'veteran_70_spouses',
            'discount_percentage'  => 70,
        ]);
        BookingGuestDetail::where('booking_id', $this->booking->id)
            ->where('sort_order', 0)
            ->update(['national_id' => '4440123456']);

        $this->booking->services()->delete();

        $quotaService = BookingService::create([
            'booking_id'                 => $this->booking->id,
            'guest_sort_order'           => 0,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                       => 'استخر',
            'unit_price'                 => 200_000,
            'quantity'                   => 1,
            'total'                      => 200_000,
            'excluded_from_veteran_quota' => false,
            'sort_order'                 => 0,
        ]);

        $excludedService = BookingService::create([
            'booking_id'                 => $this->booking->id,
            'guest_sort_order'           => 0,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                       => 'استخر VIP',
            'unit_price'                 => 200_000,
            'quantity'                   => 1,
            'total'                      => 200_000,
            'excluded_from_veteran_quota' => true,
            'manual_discount_percentage' => 25,
            'manual_discount_reason'     => 'پرداخت مستقیم',
            'sort_order'                 => 1,
        ]);

        app(ManualBookingService::class)->recalculateTotals($this->booking->fresh());

        return [
            $this->booking->fresh(),
            $quotaService->fresh(),
            $excludedService->fresh(),
        ];
    }

    private function seedVeteranBookingWithAllExcludedServices(): void
    {
        $pool = $this->veteranCatalog($this->accommodation, 'pool');
        $variant = ServiceCatalogVariant::create([
            'service_catalog_id' => $pool->id,
            'key'                => 'pool_board_all_excluded',
            'name'               => 'استخر مستثنی',
            'price'              => 200_000,
            'sort_order'         => 2,
            'is_active'          => true,
        ]);

        $this->booking->update([
            'veteran_type_applied' => 'veteran_70_spouses',
            'discount_percentage'  => 70,
        ]);
        BookingGuestDetail::where('booking_id', $this->booking->id)
            ->where('sort_order', 0)
            ->update(['national_id' => '4440123456']);

        $this->booking->services()->delete();

        BookingService::create([
            'booking_id'                 => $this->booking->id,
            'guest_sort_order'           => 0,
            'service_catalog_id'         => $pool->id,
            'service_catalog_variant_id' => $variant->id,
            'name'                       => 'استخر VIP',
            'unit_price'                 => 200_000,
            'quantity'                   => 1,
            'total'                      => 200_000,
            'excluded_from_veteran_quota' => true,
            'sort_order'                 => 0,
        ]);

        app(ManualBookingService::class)->recalculateTotals($this->booking->fresh());
    }
}
