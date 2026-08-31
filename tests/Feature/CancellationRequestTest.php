<?php

namespace Tests\Feature;

use App\Livewire\Admin\BookingShow as AdminBookingShow;
use App\Livewire\Admin\CancellationRequestIndex as AdminCancellationRequestIndex;
use App\Livewire\Host\BookingShow as HostBookingShow;
use App\Livewire\Host\CancellationRequestIndex as HostCancellationRequestIndex;
use App\Livewire\Pages\BookingShow as GuestBookingShow;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\CancellationReason;
use App\Models\CancellationRequest;
use App\Models\RefundPolicyTier;
use App\Models\PlatformCommissionEntry;
use App\Models\User;
use App\Services\CancellationRequestService;
use App\Services\ManualBookingService;
use App\Services\RefundPolicyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CancellationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Accommodation $accommodation;
    private Accommodation $otherAccommodation;
    private User $admin;
    private User $host;
    private User $otherHost;
    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->otherAccommodation = $this->createTestAccommodation(['name' => 'اقامتگاه دیگر']);

        $this->admin = User::create(['name' => 'ادمین', 'mobile' => '09120000001']);
        $this->admin->assignRole('super_admin');

        $this->host = User::create(['name' => 'میزبان', 'mobile' => '09120000002']);
        $this->host->assignRole('host');
        $this->accommodation->hosts()->attach($this->host->id);

        $this->otherHost = User::create(['name' => 'میزبان دیگر', 'mobile' => '09120000003']);
        $this->otherHost->assignRole('host');
        $this->otherAccommodation->hosts()->attach($this->otherHost->id);

        $this->guest = User::create(['name' => 'مهمان', 'mobile' => '09120000004']);
        $this->guest->assignRole('guest');
    }

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id'           => $this->guest->id,
            'accommodation_id'  => $this->accommodation->id,
            'check_in'          => '2026-07-24',
            'check_out'         => '2026-07-27',
            'nights'            => 3,
            'guests'            => 2,
            'base_price'        => 3_000_000,
            'services_subtotal' => 0,
            'total_price'       => 3_000_000,
            'status'            => 'confirmed',
            'booking_source'    => 'online',
            'tracking_code'     => 'TST-' . strtoupper(substr(md5(uniqid()), 0, 8)),
        ], $overrides));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Refund percentage tiers ──────────────────────────────────────────

    public function test_refund_percentage_tiers_match_seeded_defaults(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');
        $policy = app(RefundPolicyService::class)->forAccommodation($this->accommodation->id);

        // 7 days before check-in (2026-07-26) → >5 days bracket → 100%
        $this->assertSame(100, $policy->refundPercentageForDays(7));
        // 5 → 3 days bracket → 80%
        $this->assertSame(80, $policy->refundPercentageForDays(5));
        $this->assertSame(80, $policy->refundPercentageForDays(3));
        // 1 → 2 days bracket → 70%
        $this->assertSame(70, $policy->refundPercentageForDays(1));
        // Same day → 60%
        $this->assertSame(60, $policy->refundPercentageForDays(0));
        // After check-in (mid-stay) → 50%
        $this->assertSame(50, $policy->refundPercentageForDays(-1));
        $this->assertSame(50, $policy->refundPercentageForDays(-2));
    }

    public function test_days_before_checkin_and_amount_preview_computed_correctly(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');

        // check_in is 5 days away → 80% tier
        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 1_000_000]);

        $preview = app(RefundPolicyService::class)->previewForBooking($booking);

        $this->assertSame(5, $preview['days']);
        $this->assertSame(80, $preview['percentage']);
        $this->assertSame(800_000, $preview['amount']);
        $this->assertFalse($preview['is_mid_stay']);
        $this->assertSame(1_000_000, $preview['basis_amount']);
    }

    public function test_mid_stay_refund_is_based_on_remaining_nights_then_policy_percentage(): void
    {
        // 5-night stay: check_in 2026-07-20 → check_out 2026-07-25
        // After 2 nights have elapsed (today = 2026-07-22): remaining = 3 nights
        // Mid-stay tier = 50% → amount = (5_000_000 * 3/5) * 50% = 1_500_000
        Carbon::setTestNow('2026-07-22 10:00:00');

        $booking = $this->makeBooking([
            'check_in'    => '2026-07-20',
            'check_out'   => '2026-07-25',
            'nights'      => 5,
            'total_price' => 5_000_000,
        ]);

        $preview = app(RefundPolicyService::class)->previewForBooking($booking);

        $this->assertSame(-2, $preview['days']);
        $this->assertTrue($preview['is_mid_stay']);
        $this->assertSame(5, $preview['nights_total']);
        $this->assertSame(2, $preview['nights_elapsed']);
        $this->assertSame(3, $preview['nights_remaining']);
        $this->assertSame(50, $preview['percentage']);
        $this->assertSame(3_000_000, $preview['basis_amount']);
        $this->assertSame(1_500_000, $preview['amount']);

        $reason = $this->cancellationReasonFor($this->accommodation);
        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
        ], $this->guest);

        $this->assertSame(1_500_000, $request->refund_amount);
        $this->assertSame(50, $request->refund_percentage);
        $this->assertTrue($request->isMidStay());
        $this->assertSame(3, $request->nightsRemaining());
    }

    public function test_cancellation_allowed_on_checkout_day_but_blocked_after(): void
    {
        $booking = $this->makeBooking([
            'check_in'  => '2026-07-20',
            'check_out' => '2026-07-25',
            'nights'    => 5,
        ]);

        Carbon::setTestNow('2026-07-25 09:00:00');
        $this->assertFalse($booking->cancellationWindowClosed());
        $this->assertTrue($booking->canRequestCancellation());

        Carbon::setTestNow('2026-07-26 00:00:01');
        $this->assertTrue($booking->fresh()->cancellationWindowClosed());
        $this->assertFalse($booking->fresh()->canRequestCancellation());

        $this->expectException(ValidationException::class);

        app(CancellationRequestService::class)->create($booking->fresh(), [
            'cancellation_reason_id' => $this->cancellationReasonFor($this->accommodation)->id,
            'refund_account_number'  => '6104337812345678',
        ], $this->guest);
    }

    public function test_cancellation_panel_hidden_after_checkout_when_no_requests_exist(): void
    {
        Carbon::setTestNow('2026-07-26 10:00:00');
        $booking = $this->makeBooking([
            'check_in'  => '2026-07-20',
            'check_out' => '2026-07-25',
            'nights'    => 5,
        ]);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            // Sidebar still has the global nav link; the booking-level panel/button must be gone.
            ->assertDontSee('ثبت درخواست کنسلی')
            ->assertDontSee('تا کنون درخواست کنسلی برای این رزرو ثبت نشده است.');
    }

    // ── CancellationRequestService::create ───────────────────────────────

    public function test_create_blocks_when_booking_not_confirmed(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking(['status' => 'pending']);

        $this->expectException(ValidationException::class);

        app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $this->cancellationReasonFor($this->accommodation)->id,
            'refund_account_number'  => '6104337812345678',
        ], $this->guest);
    }

    public function test_create_requires_custom_text_when_reason_is_custom(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $customReason = $this->cancellationReasonFor($this->accommodation, custom: true);

        $this->expectException(ValidationException::class);

        app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $customReason->id,
            'custom_reason_text'     => '',
            'refund_account_number'  => '6104337812345678',
        ], $this->guest);
    }

    public function test_create_succeeds_and_snapshots_refund_details(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $reason = $this->cancellationReasonFor($this->accommodation);

        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id'      => $reason->id,
            'refund_account_number'       => '۶۱۰۴-۳۳۷۸-۱۲۳۴-۵۶۷۸', // Persian digits + dashes on purpose
            'refund_account_holder_name'  => 'مهمان تست',
            'notes'                       => 'یادداشت تست',
        ], $this->guest);

        $this->assertSame('pending', $request->status);
        $this->assertSame(5, $request->days_before_checkin);
        $this->assertSame(80, $request->refund_percentage);
        $this->assertSame(1_600_000, $request->refund_amount);
        $this->assertSame('6104-3378-1234-5678', $request->refund_account_number);
        $this->assertSame($this->guest->id, $request->requested_by);
        $this->assertTrue($booking->fresh()->hasPendingCancellationRequest());
        $this->assertFalse($booking->fresh()->canRequestCancellation());
    }

    public function test_create_blocks_duplicate_pending_request(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
        ], $this->guest);

        $this->expectException(ValidationException::class);

        app(CancellationRequestService::class)->create($booking->fresh(), [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
        ], $this->guest);
    }

    // ── Guest Livewire flow ───────────────────────────────────────────────

    public function test_guest_can_submit_cancellation_request_via_livewire(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->guest)
            ->test(GuestBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors()
            ->assertSet('showCancellationRequestModal', false);

        $this->assertSame(1, CancellationRequest::where('booking_id', $booking->id)->count());
    }

    public function test_guest_submission_fails_without_reason(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();

        Livewire::actingAs($this->guest)
            ->test(GuestBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('refundAccountNumber', '6104337812345678')
            ->call('submitCancellationRequest')
            ->assertHasErrors(['cancellationReasonId']);

        $this->assertSame(0, CancellationRequest::where('booking_id', $booking->id)->count());
    }

    public function test_host_can_submit_cancellation_request_with_default_refund_amount(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $reason = $this->cancellationReasonFor($this->accommodation);
        $expectedAmount = app(RefundPolicyService::class)->previewForBooking($booking)['amount'];

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->assertSet('cancellationRefundAmount', $expectedAmount)
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors();

        $request = CancellationRequest::where('booking_id', $booking->id)->first();
        $this->assertSame($expectedAmount, $request->refund_amount);
    }

    public function test_host_can_override_refund_amount_on_cancellation_request(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->set('cancellationRefundAmount', 500_000)
            ->call('submitCancellationRequest')
            ->assertHasNoErrors();

        $this->assertSame(500_000, CancellationRequest::where('booking_id', $booking->id)->value('refund_amount'));
    }

    public function test_admin_can_submit_cancellation_request_with_default_refund_amount(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $reason = $this->cancellationReasonFor($this->accommodation);
        $expectedAmount = app(RefundPolicyService::class)->previewForBooking($booking)['amount'];

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->assertSet('cancellationRefundAmount', $expectedAmount)
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors();

        $this->assertSame($expectedAmount, CancellationRequest::where('booking_id', $booking->id)->value('refund_amount'));
    }

    public function test_guest_cannot_approve_or_reject_requests(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        Livewire::actingAs($this->guest)
            ->test(GuestBookingShow::class, ['booking' => $booking])
            ->call('approveCancellationRequest', $request->id)
            ->assertStatus(403);
    }

    // ── Admin approve / reject / settle ──────────────────────────────────

    public function test_admin_approving_request_cancels_booking_and_reverses_commission(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');

        $manualBooking = app(ManualBookingService::class)->create(
            $this->accommodation,
            [
                'check_in'             => '2026-07-24',
                'check_out'            => '2026-07-27',
                'guests'               => 1,
                'children_under_6'     => 0,
                'veteran_type'         => null,
                'booker_national_id'   => '1234567890',
                'guest_contact_name'   => 'مهمان تست',
                'guest_contact_mobile' => '09121234567',
                'payment_method'       => 'cash',
                'services'             => [],
                'guest_details'        => [
                    ['full_name' => 'مهمان تست', 'national_id' => '1234567890', 'mobile' => '09121234567', 'relation' => ''],
                ],
            ],
            $this->admin,
        );

        $this->assertGreaterThan(0, PlatformCommissionEntry::where('booking_id', $manualBooking->id)->count());

        $request = $this->createPendingRequest($manualBooking);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $manualBooking])
            ->call('approveCancellationRequest', $request->id)
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', true)
            ->assertSet('settlingRequestId', $request->id)
            ->assertSet('settleAmount', $request->fresh()->refund_amount);

        $this->assertSame('cancelled', $manualBooking->fresh()->status);
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->decided_at);
        $this->assertSame($this->admin->id, $request->fresh()->decided_by);

        $reversals = PlatformCommissionEntry::query()
            ->where('booking_id', $manualBooking->id)
            ->where('entry_type', PlatformCommissionEntry::TYPE_REVERSAL)
            ->count();
        $this->assertGreaterThan(0, $reversals);
    }

    public function test_admin_rejecting_request_requires_reason(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            ->call('submitCancellationReject', $request->id, '');

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_admin_rejecting_request_with_reason_succeeds(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            ->call('submitCancellationReject', $request->id, 'مغایرت با شرایط لغو')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertSame('مغایرت با شرایط لغو', $request->rejection_reason);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_jalali_settlement_datetime_is_parsed_for_storage(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);
        app(CancellationRequestService::class)->approve($request->fresh(), $this->admin);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking->fresh()])
            ->call('openSettleModal', $request->id)
            ->set('settleDepositedDate', '1404/04/28')
            ->set('settleDepositedTime', '15:45')
            ->call('submitSettle')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertNotNull($request->settled_at);
        $this->assertSame(15, $request->settled_at->hour);
        $this->assertSame(45, $request->settled_at->minute);
    }

    public function test_settling_requires_approved_status_first(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            ->call('openSettleModal', $request->id);

        $this->assertNull($request->fresh()->settled_at);

        app(CancellationRequestService::class)->approve($request->fresh(), $this->admin);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking->fresh()])
            ->call('openSettleModal', $request->id)
            ->assertSet('showSettleModal', true)
            ->assertSet('settleAmount', $request->fresh()->refund_amount)
            ->assertSet('settleAccountNumber', $request->fresh()->refund_account_number)
            ->call('submitSettle')
            ->assertHasNoErrors();

        $request->refresh();
        $this->assertNotNull($request->settled_at);
        $this->assertSame($this->admin->id, $request->settled_by);
        $this->assertSame($request->refund_amount, $request->settled_amount);
        $this->assertSame($request->refund_account_number, $request->settled_account_number);
    }

    // ── Host scoping ──────────────────────────────────────────────────────

    public function test_host_can_approve_request_for_own_accommodation(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->call('approveCancellationRequest', $request->id)
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', true)
            ->assertSet('settlingRequestId', $request->id)
            ->assertSet('settleAmount', $request->fresh()->refund_amount);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_host_cannot_view_or_act_on_other_hosts_booking(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $this->createPendingRequest($booking);

        Livewire::actingAs($this->otherHost)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->assertStatus(403);
    }

    public function test_host_cancellation_request_index_is_scoped_to_managed_accommodations(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $ownBooking = $this->makeBooking();
        $this->createPendingRequest($ownBooking);

        $otherBooking = $this->makeBooking(['accommodation_id' => $this->otherAccommodation->id]);
        $this->createPendingRequest($otherBooking);

        Livewire::actingAs($this->host)
            ->test(HostCancellationRequestIndex::class)
            ->assertSee($ownBooking->tracking_code)
            ->assertDontSee($otherBooking->tracking_code);
    }

    public function test_admin_cancellation_request_index_lists_all_and_can_approve(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        Livewire::actingAs($this->admin)
            ->test(AdminCancellationRequestIndex::class)
            ->assertSee($booking->tracking_code)
            ->call('approve', $request->id)
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', true)
            ->assertSet('settlingRequestId', $request->id)
            ->assertSet('settleAmount', $request->fresh()->refund_amount);

        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_refund_policy_is_scoped_per_accommodation(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        RefundPolicyTier::query()
            ->forAccommodation($this->otherAccommodation->id)
            ->where('key', 'tier_more_than_5_days')
            ->update(['refund_percentage' => 10]);

        app(RefundPolicyService::class)->clearCache($this->otherAccommodation->id);

        $ownBooking = $this->makeBooking(['check_in' => '2026-07-26', 'total_price' => 1_000_000]);
        $otherBooking = $this->makeBooking([
            'accommodation_id' => $this->otherAccommodation->id,
            'check_in'         => '2026-07-26',
            'total_price'      => 1_000_000,
        ]);

        $ownPreview = app(RefundPolicyService::class)->previewForBooking($ownBooking);
        $otherPreview = app(RefundPolicyService::class)->previewForBooking($otherBooking);

        $this->assertSame(100, $ownPreview['percentage']);
        $this->assertSame(10, $otherPreview['percentage']);
    }

    // ── Zero-refund auto-completion ───────────────────────────────────────

    public function test_zero_percent_policy_tier_produces_zero_refund_amount(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');

        RefundPolicyTier::query()
            ->forAccommodation($this->accommodation->id)
            ->update(['refund_percentage' => 0]);

        app(RefundPolicyService::class)->clearCache($this->accommodation->id);

        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $preview = app(RefundPolicyService::class)->previewForBooking($booking);

        $this->assertSame(0, $preview['percentage']);
        $this->assertSame(0, $preview['amount']);
    }

    public function test_mid_stay_on_checkout_day_with_no_remaining_nights_has_zero_refund(): void
    {
        // 3-night stay ending today: no nights remain → basis = 0 → refund = 0
        Carbon::setTestNow('2026-07-27 10:00:00');

        $booking = $this->makeBooking([
            'check_in'    => '2026-07-24',
            'check_out'   => '2026-07-27',
            'nights'      => 3,
            'total_price' => 3_000_000,
        ]);

        $preview = app(RefundPolicyService::class)->previewForBooking($booking);

        $this->assertSame(0, $preview['nights_remaining']);
        $this->assertSame(0, $preview['basis_amount']);
        $this->assertSame(0, $preview['amount']);
    }

    public function test_staff_direct_submit_with_zero_override_auto_completes_entire_flow(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->set('cancellationRefundAmount', 0)
            ->call('submitCancellationRequest')
            ->assertHasNoErrors()
            ->assertSet('showCancellationRequestModal', false)
            ->assertSet('showSettleModal', false);

        $request = CancellationRequest::where('booking_id', $booking->id)->first();
        $this->assertNotNull($request);
        $this->assertSame('approved', $request->status);
        $this->assertSame(0, $request->refund_amount);
        $this->assertNotNull($request->decided_at);
        $this->assertSame($this->host->id, $request->decided_by);
        $this->assertTrue($request->isSettled());
        $this->assertSame(0, $request->settled_amount);
        $this->assertNotNull($request->settled_at);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertFalse($booking->fresh()->canRequestCancellation());
    }

    public function test_admin_direct_submit_with_zero_policy_refund_auto_completes(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');

        RefundPolicyTier::query()
            ->forAccommodation($this->accommodation->id)
            ->update(['refund_percentage' => 0]);

        app(RefundPolicyService::class)->clearCache($this->accommodation->id);

        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->assertSet('cancellationRefundAmount', 0)
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', false);

        $request = CancellationRequest::where('booking_id', $booking->id)->first();
        $this->assertTrue($request->isSettled());
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_guest_zero_refund_submission_stays_pending_until_staff_approves(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');

        RefundPolicyTier::query()
            ->forAccommodation($this->accommodation->id)
            ->update(['refund_percentage' => 0]);

        app(RefundPolicyService::class)->clearCache($this->accommodation->id);

        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->guest)
            ->test(GuestBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors();

        $request = CancellationRequest::where('booking_id', $booking->id)->first();
        $this->assertSame('pending', $request->status);
        $this->assertFalse($request->isSettled());
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_guest_zero_refund_does_not_require_account_number(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');

        RefundPolicyTier::query()
            ->forAccommodation($this->accommodation->id)
            ->update(['refund_percentage' => 0]);

        app(RefundPolicyService::class)->clearCache($this->accommodation->id);

        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->guest)
            ->test(GuestBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors();

        $request = CancellationRequest::where('booking_id', $booking->id)->first();
        $this->assertNotNull($request);
        $this->assertSame(0, $request->refund_amount);
        $this->assertSame('', $request->refund_account_number);
    }

    public function test_staff_zero_refund_override_does_not_require_account_number(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('cancellationRefundAmount', 0)
            ->set('refundAccountNumber', '')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors()
            ->assertSet('showCancellationRequestModal', false);

        $request = CancellationRequest::where('booking_id', $booking->id)->first();
        $this->assertNotNull($request);
        $this->assertSame(0, $request->refund_amount);
        $this->assertSame('', $request->refund_account_number);
        $this->assertTrue($request->isSettled());
    }

    public function test_non_zero_refund_still_requires_account_number(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->guest)
            ->test(GuestBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '')
            ->call('submitCancellationRequest')
            ->assertHasErrors(['refundAccountNumber']);
    }

    public function test_service_create_with_zero_refund_allows_empty_account_number(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '',
            'refund_amount'          => 0,
        ], $this->guest);

        $this->assertSame(0, $request->refund_amount);
        $this->assertSame('', $request->refund_account_number);
    }

    public function test_approving_zero_refund_request_auto_settles_without_settle_modal(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
            'refund_amount'          => 0,
        ], $this->guest);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            ->call('approveCancellationRequest', $request->id)
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', false);

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertTrue($request->isSettled());
        $this->assertSame(0, $request->settled_amount);
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_admin_index_approve_zero_refund_auto_settles(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
            'refund_amount'          => 0,
        ], $this->guest);

        Livewire::actingAs($this->admin)
            ->test(AdminCancellationRequestIndex::class)
            ->call('approve', $request->id)
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', false);

        $request->refresh();
        $this->assertTrue($request->isSettled());
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_host_index_approve_zero_refund_auto_settles(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        $request = app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
            'refund_amount'          => 0,
        ], $this->guest);

        Livewire::actingAs($this->host)
            ->test(HostCancellationRequestIndex::class)
            ->call('approve', $request->id)
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', false);

        $request->refresh();
        $this->assertTrue($request->isSettled());
    }

    public function test_mark_settled_without_payment_rejects_non_zero_refund(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        app(CancellationRequestService::class)->approve($request->fresh(), $this->admin);

        $this->expectException(ValidationException::class);

        app(CancellationRequestService::class)->markSettledWithoutPayment($request->fresh(), $this->admin);
    }

    public function test_legacy_stuck_zero_refund_resolved_via_open_settle_modal(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $reason = $this->cancellationReasonFor($this->accommodation);

        $request = CancellationRequest::create([
            'booking_id'             => $booking->id,
            'requested_by'           => $this->guest->id,
            'status'                   => CancellationRequest::STATUS_APPROVED,
            'cancellation_reason_id'   => $reason->id,
            'reason_text'              => $reason->label,
            'refund_account_number'    => '6104337812345678',
            'days_before_checkin'      => 5,
            'refund_percentage'        => 0,
            'refund_amount'            => 0,
            'decided_by'               => $this->admin->id,
            'decided_at'               => now(),
        ]);

        $booking->update(['status' => 'cancelled']);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking->fresh()])
            ->call('openSettleModal', $request->id)
            ->assertSet('showSettleModal', false);

        $request->refresh();
        $this->assertTrue($request->isSettled());
        $this->assertSame(0, $request->settled_amount);
    }

    public function test_non_zero_refund_still_requires_manual_settlement(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking();
        $request = $this->createPendingRequest($booking);

        Livewire::actingAs($this->admin)
            ->test(AdminBookingShow::class, ['booking' => $booking])
            ->call('approveCancellationRequest', $request->id)
            ->assertHasNoErrors()
            ->assertSet('showSettleModal', true)
            ->assertSet('settlingRequestId', $request->id);

        $request->refresh();
        $this->assertFalse($request->isSettled());
        $this->assertGreaterThan(0, $request->refund_amount);
    }

    public function test_staff_direct_submit_with_non_zero_refund_stays_pending(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');
        $booking = $this->makeBooking(['check_in' => '2026-07-24', 'total_price' => 2_000_000]);
        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $booking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->call('submitCancellationRequest')
            ->assertHasNoErrors();

        $request = CancellationRequest::where('booking_id', $booking->id)->first();
        $this->assertSame('pending', $request->status);
        $this->assertFalse($request->isSettled());
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_zero_refund_approval_reverses_commission(): void
    {
        Carbon::setTestNow('2026-07-19 08:00:00');

        $manualBooking = app(ManualBookingService::class)->create(
            $this->accommodation,
            [
                'check_in'             => '2026-07-24',
                'check_out'            => '2026-07-27',
                'guests'               => 1,
                'children_under_6'     => 0,
                'veteran_type'         => null,
                'booker_national_id'   => '1234567890',
                'guest_contact_name'   => 'مهمان تست',
                'guest_contact_mobile' => '09121234567',
                'payment_method'       => 'cash',
                'services'             => [],
                'guest_details'        => [
                    ['full_name' => 'مهمان تست', 'national_id' => '1234567890', 'mobile' => '09121234567', 'relation' => ''],
                ],
            ],
            $this->admin,
        );

        $this->assertGreaterThan(0, PlatformCommissionEntry::where('booking_id', $manualBooking->id)->count());

        $reason = $this->cancellationReasonFor($this->accommodation);

        Livewire::actingAs($this->host)
            ->test(HostBookingShow::class, ['booking' => $manualBooking])
            ->call('openCancellationRequestModal')
            ->set('cancellationReasonId', (string) $reason->id)
            ->set('refundAccountNumber', '6104337812345678')
            ->set('cancellationRefundAmount', 0)
            ->call('submitCancellationRequest')
            ->assertHasNoErrors();

        $this->assertSame('cancelled', $manualBooking->fresh()->status);

        $reversals = PlatformCommissionEntry::query()
            ->where('booking_id', $manualBooking->id)
            ->where('entry_type', PlatformCommissionEntry::TYPE_REVERSAL)
            ->count();
        $this->assertGreaterThan(0, $reversals);
    }

    private function cancellationReasonFor(Accommodation $accommodation, bool $custom = false): CancellationReason
    {
        return CancellationReason::query()
            ->forAccommodation($accommodation->id)
            ->where('is_custom', $custom)
            ->firstOrFail();
    }

    private function createPendingRequest(Booking $booking): CancellationRequest
    {
        $reason = $this->cancellationReasonFor(
            Accommodation::query()->findOrFail($booking->accommodation_id),
        );

        return app(CancellationRequestService::class)->create($booking, [
            'cancellation_reason_id' => $reason->id,
            'refund_account_number'  => '6104337812345678',
        ], $this->guest);
    }
}
