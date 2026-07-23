<?php

namespace Tests\Feature;

use App\Livewire\BookingServicesEditor;
use App\Livewire\Host\BookingShow;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingEditWindowTest extends TestCase
{
    use RefreshDatabase;

    private User $host;
    private User $admin;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $accommodation = $this->createTestAccommodation();

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09120000111']);
        $this->host->assignRole('host');
        $accommodation->hosts()->attach($this->host->id);

        $this->admin = User::create(['name' => 'ادمین', 'mobile' => '09120000222']);
        $this->admin->assignRole('super_admin');

        $guest = User::create(['name' => 'مهمان', 'mobile' => '09121113333']);
        $guest->assignRole('guest');

        $this->booking = Booking::create([
            'user_id'          => $guest->id,
            'accommodation_id' => $accommodation->id,
            'booking_source'   => 'manual',
            'check_in'         => '2026-07-10',
            'check_out'        => '2026-07-15',
            'nights'           => 5,
            'guests'           => 1,
            'base_price'       => 1_000_000,
            'services_subtotal'=> 0,
            'total_price'      => 1_000_000,
            'status'           => 'confirmed',
            'tracking_code'    => 'TST-EDIT-WIN',
        ]);

        BookingService::create([
            'booking_id'   => $this->booking->id,
            'name'         => 'صبحانه',
            'unit_price'   => 100_000,
            'quantity'     => 1,
            'total'        => 100_000,
            'sort_order'   => 0,
        ]);
    }

    public function test_booking_is_editable_through_check_out_day(): void
    {
        Carbon::setTestNow('2026-07-15 18:00:00');

        $this->assertTrue($this->booking->isWithinBookingEditWindow());
        $this->assertTrue($this->booking->canEditBookingDetails($this->host));
    }

    public function test_booking_is_not_editable_after_check_out_day_for_host(): void
    {
        Carbon::setTestNow('2026-07-16 09:00:00');

        $this->assertFalse($this->booking->isWithinBookingEditWindow());
        $this->assertFalse($this->booking->canEditBookingDetails($this->host));
    }

    public function test_admin_can_edit_booking_after_check_out_day(): void
    {
        Carbon::setTestNow('2026-07-16 09:00:00');

        $this->assertTrue($this->booking->canEditBookingDetails($this->admin));
    }

    public function test_host_cannot_mount_services_editor_after_check_out_day(): void
    {
        Carbon::setTestNow('2026-07-16 09:00:00');

        Livewire::actingAs($this->host)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $this->booking->id,
                'panel'     => 'host',
            ])
            ->assertStatus(422);
    }

    public function test_host_cannot_add_service_after_check_out_day(): void
    {
        Carbon::setTestNow('2026-07-16 09:00:00');

        Livewire::actingAs($this->host)
            ->test(BookingShow::class, ['booking' => $this->booking])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'خدمت جدید')
            ->set('newServicePrice', 50_000)
            ->set('newServiceQty', 1)
            ->call('addServiceLine')
            ->assertForbidden();

        $this->assertSame(1, $this->booking->fresh()->services()->count());
    }

    public function test_admin_can_add_service_after_check_out_day(): void
    {
        Carbon::setTestNow('2026-07-16 09:00:00');

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $this->booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'خدمت ادمین')
            ->set('newServicePrice', 80_000)
            ->set('newServiceQty', 1)
            ->call('addServiceLine')
            ->assertDispatched('booking-services-updated');

        $this->assertTrue(
            $this->booking->fresh()->services()->where('name', 'خدمت ادمین')->exists()
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
