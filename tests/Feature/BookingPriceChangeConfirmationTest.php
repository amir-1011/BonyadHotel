<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingShow;
use App\Livewire\BookingServicesEditor;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Program;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingPriceChangePreviewService;
use App\Services\BookingReceiptBreakdownService;
use App\Services\ManualBookingService;
use App\Services\ProgramBookingService;
use Carbon\Carbon;
use Database\Seeders\VeteranPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingPriceChangeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $physicalRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VeteranPolicySeeder::class);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name'   => 'ادمین',
            'mobile' => '09000002222',
        ]);
        $this->admin->assignRole('super_admin');

        $accommodation = $this->createTestAccommodation(['name' => 'اقامتگاه تست قیمت']);
        $this->roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name'             => 'دو تخته',
            'capacity'         => 2,
            'extra_capacity'   => 1,
            'extra_capacity_price' => 100_000,
            'room_count'       => 4,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ',
            'price_per_night' => 1_000_000,
            'is_active'       => true,
        ]);
        $this->physicalRoom = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => 'P-101',
            'is_active'    => true,
        ]);
    }

    public function test_preview_reports_positive_delta_when_adding_service(): void
    {
        $booking = $this->createManualBooking();

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'صبحانه')
            ->set('newServicePrice', 200_000)
            ->set('newServiceQty', 1)
            ->call('previewBookingPriceChange', 'addServiceLine', [])
            ->assertReturned(fn (array $result) => $result['error'] === false
                && $result['affects_price'] === true
                && $result['auto_delta'] === 200_000);
    }

    public function test_execute_confirmed_price_change_applies_custom_delta_override(): void
    {
        $booking = $this->createManualBooking();
        $beforeTotal = (int) $booking->total_price;

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'صبحانه')
            ->set('newServicePrice', 200_000)
            ->set('newServiceQty', 1)
            ->call('executeConfirmedPriceChange', 'addServiceLine', 50_000, []);

        $booking->refresh();
        $this->assertSame($beforeTotal + 50_000, (int) $booking->total_price);
        $this->assertTrue($booking->services()->where('name', 'صبحانه')->exists());
    }

    public function test_preview_skips_auto_repricing_for_program_booking(): void
    {
        $booking = $this->createProgramBooking();

        $this->assertTrue($booking->isProgram());
        $this->assertFalse(app(BookingPriceChangePreviewService::class)->bookingSupportsAutoRepricing($booking));

        $component = Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->set('extendCheckOutJalali', \Morilog\Jalali\Jalalian::fromCarbon($booking->check_out->copy()->addDay())->format('Y/m/d'))
            ->call('previewBookingPriceChange', 'extendStayCheckout', []);

        $result = $component->effects['returns'][0] ?? null;
        $this->assertIsArray($result);
        $this->assertFalse($result['error']);
        $this->assertFalse($result['affects_price']);
        $this->assertSame(0, $result['auto_delta']);
    }

    public function test_remove_service_preview_reports_negative_delta(): void
    {
        $booking = $this->createManualBooking();
        $service = BookingService::create([
            'booking_id'  => $booking->id,
            'name'        => 'استخر',
            'unit_price'  => 300_000,
            'quantity'    => 1,
            'total'       => 300_000,
            'sort_order'  => 0,
        ]);
        app(ManualBookingService::class)->recalculateTotals($booking->fresh());
        $booking = $booking->fresh();

        $component = Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->call('previewBookingPriceChange', 'removeServiceLine', ['serviceId' => $service->id])
            ->assertReturned(fn (array $result) => $result['error'] === false
                && $result['affects_price'] === true
                && $result['auto_delta'] === -300_000);

        $this->assertTrue(BookingService::whereKey($service->id)->exists());
        $component->assertSet("editableServices.{$service->id}.name", 'استخر');
        $this->assertTrue($component->get('booking')->services->contains('id', $service->id));
        $this->assertSame((int) $booking->total_price, (int) $component->get('booking')->total_price);

        $component
            ->call('$refresh')
            ->assertSee('استخر')
            ->assertDontSee('هنوز خدمتی برای این رزرو ثبت نشده است');
    }

    public function test_revert_service_quota_ui_restores_saved_values(): void
    {
        $booking = $this->createManualBooking();
        $service = BookingService::create([
            'booking_id'                  => $booking->id,
            'name'                        => 'صبحانه',
            'unit_price'                  => 100_000,
            'quantity'                    => 1,
            'total'                       => 100_000,
            'sort_order'                  => 0,
            'excluded_from_veteran_quota' => false,
        ]);
        app(ManualBookingService::class)->recalculateTotals($booking->fresh());
        $booking = $booking->fresh();

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set("editableServices.{$service->id}.excluded_from_veteran_quota", true)
            ->call('revertServiceQuotaUi', $service->id)
            ->assertSet("editableServices.{$service->id}.excluded_from_veteran_quota", false);
    }

    public function test_add_room_preview_reports_price_increase(): void
    {
        $booking = $this->createManualBooking();
        $beforeTotal = (int) $booking->total_price;
        $secondRoom = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => 'P-102',
            'is_active'    => true,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking])
            ->set('addRoomRoomTypeId', (string) $this->roomType->id)
            ->set('addRoomRoomRateId', (string) $this->roomRate->id)
            ->set('addRoomAdults', 1)
            ->set('addRoomPhysicalRoomId', $secondRoom->id)
            ->call('previewBookingPriceChange', 'commitAddRoomLine', []);

        $result = $component->effects['returns'][0] ?? null;
        $this->assertIsArray($result);
        $this->assertFalse($result['error']);
        $this->assertTrue($result['affects_price']);
        $this->assertGreaterThan(0, $result['auto_delta']);
        $this->assertSame($beforeTotal, $result['current_total']);
        $this->assertSame($beforeTotal + $result['auto_delta'], $result['projected_total']);
    }

    public function test_preview_delta_is_incremental_after_manual_total_override(): void
    {
        $booking = $this->createManualBooking();
        $naturalTotal = (int) $booking->total_price;
        $manualBump = 500_000;

        $booking->update(['total_price' => $naturalTotal + $manualBump]);
        $booking = $booking->fresh();
        $displayTotal = (int) $booking->total_price;

        $preview = app(BookingPriceChangePreviewService::class)->preview(
            $booking,
            function (Booking $target) {
                BookingService::create([
                    'booking_id' => $target->id,
                    'name'        => 'صبحانه',
                    'unit_price'  => 200_000,
                    'quantity'    => 1,
                    'total'       => 200_000,
                    'sort_order'  => $target->services()->count(),
                ]);
                app(ManualBookingService::class)->recalculateTotals($target->fresh());
            },
        );

        $this->assertSame($displayTotal, $preview['current_total']);
        $this->assertSame(200_000, $preview['auto_delta']);
        $this->assertSame($displayTotal + 200_000, $preview['projected_total']);
        $this->assertGreaterThan(0, $preview['auto_delta']);
    }

    public function test_second_service_preview_stays_positive_after_prior_manual_inflation(): void
    {
        $booking = $this->createManualBooking();
        $beforeFirstChange = (int) $booking->total_price;

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'استخر')
            ->set('newServicePrice', 300_000)
            ->set('newServiceQty', 1)
            ->call('executeConfirmedPriceChange', 'addServiceLine', 800_000, []);

        $booking = $booking->fresh();
        $this->assertSame($beforeFirstChange + 800_000, (int) $booking->total_price);

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'صبحانه')
            ->set('newServicePrice', 150_000)
            ->set('newServiceQty', 1)
            ->call('previewBookingPriceChange', 'addServiceLine', [])
            ->assertReturned(fn (array $result) => $result['error'] === false
                && $result['current_total'] === (int) $booking->total_price
                && $result['auto_delta'] === 150_000
                && $result['projected_total'] === (int) $booking->total_price + 150_000);
    }

    public function test_remove_service_preview_stays_incremental_after_manual_override(): void
    {
        $booking = $this->createManualBooking();
        $service = BookingService::create([
            'booking_id' => $booking->id,
            'name'       => 'استخر',
            'unit_price' => 300_000,
            'quantity'   => 1,
            'total'      => 300_000,
            'sort_order' => 0,
        ]);
        app(ManualBookingService::class)->recalculateTotals($booking->fresh());
        $booking = $booking->fresh();

        $naturalTotal = (int) $booking->total_price;
        $booking->update(['total_price' => $naturalTotal + 400_000]);
        $booking = $booking->fresh();
        $displayTotal = (int) $booking->total_price;

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->call('previewBookingPriceChange', 'removeServiceLine', ['serviceId' => $service->id])
            ->assertReturned(fn (array $result) => $result['error'] === false
                && $result['current_total'] === $displayTotal
                && $result['auto_delta'] === -300_000
                && $result['projected_total'] === $displayTotal - 300_000);
    }

    public function test_chained_execute_keeps_adding_on_latest_display_total(): void
    {
        $booking = $this->createManualBooking();
        $startTotal = (int) $booking->total_price;

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'اول')
            ->set('newServicePrice', 100_000)
            ->set('newServiceQty', 1)
            ->call('executeConfirmedPriceChange', 'addServiceLine', 250_000, []);

        $booking = $booking->fresh();
        $this->assertSame($startTotal + 250_000, (int) $booking->total_price);

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'دوم')
            ->set('newServicePrice', 100_000)
            ->set('newServiceQty', 1)
            ->call('executeConfirmedPriceChange', 'addServiceLine', 75_000, []);

        $booking->refresh();
        $this->assertSame($startTotal + 250_000 + 75_000, (int) $booking->total_price);
    }

    public function test_pricing_breakdown_exposes_manual_total_adjustment(): void
    {
        $booking = $this->createManualBooking();
        $naturalTotal = (int) $booking->total_price;
        $manualBump = 350_000;

        $booking->update(['total_price' => $naturalTotal + $manualBump]);

        $pricing = app(BookingReceiptBreakdownService::class)->pricingForBooking($booking->fresh());

        $this->assertTrue($pricing['has_manual_total_adjustment']);
        $this->assertSame($naturalTotal, $pricing['natural_total']);
        $this->assertSame($naturalTotal + $manualBump, $pricing['payable_total']);
        $this->assertSame($manualBump, $pricing['manual_total_adjustment']);
    }

    public function test_booking_show_renders_manual_adjustment_labels(): void
    {
        $booking = $this->createManualBooking();
        $naturalTotal = (int) $booking->total_price;
        $manualBump = 400_000;

        $booking->update(['total_price' => $naturalTotal + $manualBump]);

        Livewire::actingAs($this->admin)
            ->test(BookingShow::class, ['booking' => $booking->fresh()])
            ->assertSee('تعدیل مبلغ +400,000')
            ->assertSee('محاسبه خودکار: ' . number_format($naturalTotal))
            ->assertSee('تعدیل مبلغ رزرو');
    }

    public function test_confirmed_custom_delta_surfaces_manual_adjustment_in_breakdown(): void
    {
        $booking = $this->createManualBooking();
        $startTotal = (int) $booking->total_price;

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'استخر')
            ->set('newServicePrice', 100_000)
            ->set('newServiceQty', 1)
            ->call('executeConfirmedPriceChange', 'addServiceLine', 500_000, []);

        $booking = $booking->fresh();
        $pricing = app(BookingReceiptBreakdownService::class)->pricingForBooking($booking);

        $this->assertSame($startTotal + 500_000, (int) $booking->total_price);
        $this->assertSame(400_000, $pricing['manual_total_adjustment']);
        $this->assertSame((int) $pricing['natural_total'] + 400_000, (int) $pricing['payable_total']);
    }

    public function test_service_manual_price_adjustment_is_stored_and_shown_in_financial_breakdown(): void
    {
        $booking = $this->createManualBooking();
        $startTotal = (int) $booking->total_price;

        Livewire::actingAs($this->admin)
            ->test(BookingServicesEditor::class, [
                'bookingId' => $booking->id,
                'panel'     => 'admin',
            ])
            ->set('newServiceCatalogId', 'custom')
            ->set('newServiceName', 'استخر')
            ->set('newServicePrice', 100_000)
            ->set('newServiceQty', 1)
            ->call('executeConfirmedPriceChange', 'addServiceLine', 500_000, []);

        $booking = $booking->fresh()->load('services', 'guestDetails', 'accommodation', 'bookingRooms.roomType');
        $service = $booking->services->first();

        $this->assertNotNull($service);
        $this->assertSame(400_000, (int) $service->manual_price_adjustment);
        $this->assertSame(500_000, $service->payableTotal());
        $this->assertSame($startTotal + 500_000, (int) $booking->total_price);

        $html = view('components.booking.financial-breakdown', [
            'booking' => $booking,
            'pricing' => app(BookingReceiptBreakdownService::class)->pricingForBooking($booking),
        ])->render();

        $this->assertStringContainsString('مبلغ این خدمت', $html);
        $this->assertStringContainsString('تعدیل مبلغ', $html);
        $this->assertStringContainsString('سرجمع', $html);
        $this->assertStringContainsString(number_format(500_000), $html);
    }

    private function createManualBooking(): Booking
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        return app(ManualBookingService::class)->create(
            $this->roomType->accommodation,
            [
                'check_in'   => $checkIn,
                'check_out'  => $checkOut,
                'room_lines' => [
                    [
                        'room_type_id'     => $this->roomType->id,
                        'room_rate_id'     => $this->roomRate->id,
                        'room_id'          => $this->physicalRoom->id,
                        'adults'           => 1,
                        'children_under_6' => 0,
                        'guests'           => 1,
                        'extra_guests'     => 0,
                        'bill_full_rooms'  => false,
                    ],
                ],
                'booker_national_id'   => '1234567890',
                'guest_contact_name'   => 'مهمان',
                'guest_contact_mobile' => '09121111111',
                'payment_method'       => 'cash',
                'services'             => [],
                'guest_details'        => [
                    [
                        'full_name'   => 'مهمان',
                        'national_id' => '1234567890',
                        'mobile'      => '09121111111',
                        'relation'    => '',
                    ],
                ],
            ],
            $this->admin,
        )->fresh();
    }

    private function createProgramBooking(): Booking
    {
        $employer = ProgramEmployer::create([
            'province_id'             => $this->ensureTestProvinceId(),
            'name'                    => 'کارفرما',
            'employer_code'           => '515110',
            'national_or_economic_id' => '1122334455',
            'mobile'                  => '09123334455',
        ]);

        $checkIn = now()->addDays(5)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays(2)->format('Y-m-d');

        $program = app(ProgramBookingService::class)->create(
            $this->roomType->accommodation->fresh(),
            [
                'title'               => 'اردو',
                'program_type'        => Program::TYPE_CAMP,
                'program_employer_id' => $employer->id,
                'guest_count'         => 1,
                'rooms_allocated'     => 1,
                'check_in'            => $checkIn,
                'check_out'           => $checkOut,
                'base_price'          => 2_000_000,
                'discount_amount'     => 0,
                'deposit_amount'      => 0,
                'room_lines'          => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->physicalRoom->id,
                    'room_name'    => $this->physicalRoom->name,
                ]],
                'guest_details' => [[
                    'full_name'       => 'مهمان',
                    'national_id'     => '1234567890',
                    'mobile'          => '09121111111',
                    'relation'        => 'مهمان اصلی',
                    'room_line_index' => 0,
                    'sort_order'      => 0,
                ]],
            ],
            $this->admin,
        );

        return $program->booking()->firstOrFail()->fresh();
    }
}
