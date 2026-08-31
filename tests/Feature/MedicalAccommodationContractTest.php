<?php

namespace Tests\Feature;

use App\Livewire\Admin\AccommodationMedicalAccommodationSettings;
use App\Livewire\ManualBookingForm;
use App\Models\Booking;
use App\Models\MedicalAccommodationContract;
use App\Models\MedicalAccommodationTariff;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\BookingStayExtensionService;
use App\Services\ManualBookingService;
use App\Services\MedicalAccommodationBillingService;
use App\Services\MedicalAccommodationProvisioner;
use App\Support\MedicalAccommodationContractNumbers;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MedicalAccommodationContractTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;

    private RoomType $roomType;

    private RoomRate $roomRate;

    private User $adminUser;

    private int $guestSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق تست',
            'capacity'         => 4,
            'room_count'       => 5,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active'        => true,
        ]);

        $this->adminUser = User::create([
            'name'   => 'ادمین تست',
            'mobile' => '09000000088',
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_default_contract_number_uses_jalali_year_province_code_and_sequence(): void
    {
        $contract = $this->defaultContract();
        $expected = $this->expectedNumber(1);

        $this->assertSame($expected, $contract->contract_number);
        $this->assertGreaterThan(0, $contract->tariffs()->count());
        $this->assertNotNull($contract->program_employer_id);
    }

    public function test_second_contract_increments_sequence_and_keeps_same_employer(): void
    {
        $first = $this->defaultContract();
        $second = app(MedicalAccommodationProvisioner::class)->createContract($this->accommodation, [
            'program_employer_id' => $first->program_employer_id,
            'seed_tariffs'        => true,
        ]);

        $this->assertSame($this->expectedNumber(1), $first->contract_number);
        $this->assertSame($this->expectedNumber(2), $second->contract_number);
        $this->assertSame($first->program_employer_id, $second->program_employer_id);
        $this->assertNotSame($first->id, $second->id);
        $this->assertGreaterThan(0, $second->tariffs()->count());
    }

    public function test_two_hotels_in_same_province_can_share_the_same_default_number(): void
    {
        $other = $this->createTestAccommodation(['name' => 'اقامتگاه دوم']);
        $firstNumber = $this->defaultContract()->contract_number;
        $otherNumber = MedicalAccommodationContract::query()
            ->where('accommodation_id', $other->id)
            ->orderBy('id')
            ->value('contract_number');

        $this->assertSame($firstNumber, $otherNumber);
        $this->assertSame($this->expectedNumber(1), $firstNumber);
    }

    public function test_settings_page_shows_locked_contract_number_and_swal_confirm(): void
    {
        $number = $this->defaultContract()->contract_number;

        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->assertSee('شماره قرارداد')
            ->assertSee($number)
            ->assertSee('شماره قرارداد به‌صورت خودکار توسط سامانه صادر شده است')
            ->assertSet('contracts.0.number_locked', true)
            ->assertSet('contracts.0.contract_number', $number)
            ->call('unlockContractNumber', 0)
            ->assertSet('contracts.0.number_locked', false);
    }

    public function test_user_can_override_contract_number_after_unlock(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->call('unlockContractNumber', 0)
            ->set('contracts.0.contract_number', 'DAY-1405/A1')
            ->call('saveContract', 0)
            ->assertHasNoErrors();

        $this->assertSame('DAY-1405/A1', $this->defaultContract()->fresh()->contract_number);
    }

    public function test_user_can_override_contract_number_with_persian_letters(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->call('unlockContractNumber', 0)
            ->set('contracts.0.contract_number', 'قرارداد-۱۴۰۵/الف')
            ->call('saveContract', 0)
            ->assertHasNoErrors();

        $this->assertSame('قرارداد-۱۴۰۵/الف', $this->defaultContract()->fresh()->contract_number);
    }

    public function test_duplicate_contract_number_on_same_hotel_is_rejected(): void
    {
        $first = $this->defaultContract();
        app(MedicalAccommodationProvisioner::class)->createContract($this->accommodation, [
            'program_employer_id' => $first->program_employer_id,
            'seed_tariffs'        => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->call('unlockContractNumber', 1)
            ->set('contracts.1.contract_number', $first->contract_number)
            ->call('saveContract', 1)
            ->assertHasErrors(['contracts.1.contract_number']);
    }

    public function test_livewire_can_add_a_second_contract_with_its_own_tariffs(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->call('addContract')
            ->assertHasNoErrors()
            ->assertCount('contracts', 2)
            ->assertSet('contracts.1.contract_number', $this->expectedNumber(2));

        $this->assertSame(2, MedicalAccommodationContract::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->count());
    }

    public function test_last_contract_cannot_be_deleted(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->call('removeContract', 0)
            ->assertHasNoErrors();

        $this->assertSame(1, MedicalAccommodationContract::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->count());
    }

    public function test_contract_with_bookings_cannot_be_deleted(): void
    {
        $first = $this->defaultContract();
        app(MedicalAccommodationProvisioner::class)->createContract($this->accommodation, [
            'program_employer_id' => $first->program_employer_id,
            'seed_tariffs'        => true,
        ]);

        $this->createMedicalBooking([
            'medical_contract_id' => $first->id,
            'medical_tariff_id'   => $first->tariffs()->ordered()->first()->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->call('removeContract', 0)
            ->assertHasNoErrors();

        $this->assertTrue(MedicalAccommodationContract::query()->whereKey($first->id)->exists());
        $this->assertSame(2, MedicalAccommodationContract::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->count());
    }

    public function test_manual_booking_lists_contract_numbers_and_stores_selected_contract(): void
    {
        $first = $this->defaultContract();
        $second = $this->makeDatedContract($first->program_employer_id, '2026-10-01', '2026-10-31', 'تعرفه پاییز', 4_000_000);
        $first->update(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
        $firstTariff = $first->tariffs()->ordered()->first();
        $firstTariff->update(['nightly_rate' => 2_000_000, 'label' => 'تعرفه شهریور']);

        [$checkIn, $checkOut] = ['2026-09-10', '2026-09-12'];

        $component = Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '5111222333')
            ->call('verifyBooker')
            ->set('guestContactName', 'مهمان قرارداد')
            ->set('guestContactMobile', '09151112223')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_MEDICAL_ACCOMMODATION)
            ->assertSee($first->contract_number)
            ->assertDontSee($second->contract_number)
            ->assertSee('تعرفه شهریور')
            ->assertDontSee('تعرفه پاییز');

        $this->assertSame($first->id, (int) $component->get('medicalContractId'));

        $component
            ->set('medicalTariffId', $firstTariff->id)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertSame($first->id, (int) $booking->medical_contract_id);
        $this->assertSame($first->contract_number, $booking->medicalContractNumber());
        $this->assertSame(4_000_000, (int) $booking->total_price);
    }

    public function test_selecting_a_contract_uses_that_contract_tariffs_not_another(): void
    {
        $first = $this->defaultContract();
        $second = $this->makeDatedContract($first->program_employer_id, null, null, 'تعرفه دوم', 8_000_000);
        $firstTariff = $first->tariffs()->ordered()->first();
        $firstTariff->update(['nightly_rate' => 1_000_000]);
        $secondTariff = $second->tariffs()->where('label', 'تعرفه دوم')->firstOrFail();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('تعلق ندارد');

        $this->createMedicalBooking([
            'check_in'            => '2026-09-10',
            'nights'              => 2,
            'medical_contract_id' => $second->id,
            'medical_tariff_id'   => $firstTariff->id,
        ]);
    }

    public function test_booking_outside_selected_contract_dates_is_rejected(): void
    {
        $contract = $this->defaultContract();
        $contract->update([
            'starts_on' => '2026-10-01',
            'ends_on'   => '2026-10-15',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('بازه قرارداد');

        $this->createMedicalBooking([
            'check_in'            => '2026-09-10',
            'nights'              => 2,
            'medical_contract_id' => $contract->id,
            'medical_tariff_id'   => $contract->tariffs()->ordered()->first()->id,
        ]);
    }

    public function test_two_contracts_same_employer_different_rates_are_applied_independently(): void
    {
        $first = $this->defaultContract();
        $second = $this->makeDatedContract($first->program_employer_id, null, null, 'تعرفه ویژه', 9_000_000);
        $firstTariff = $first->tariffs()->ordered()->first();
        $firstTariff->update(['nightly_rate' => 2_000_000]);
        $secondTariff = $second->tariffs()->where('label', 'تعرفه ویژه')->firstOrFail();

        $cheap = $this->createMedicalBooking([
            'booker_national_id'   => '5222333444',
            'guest_contact_name'   => 'قرارداد ارزان',
            'guest_contact_mobile' => '09152223334',
            'nights'               => 2,
            'medical_contract_id'  => $first->id,
            'medical_tariff_id'    => $firstTariff->id,
        ]);
        $expensive = $this->createMedicalBooking([
            'booker_national_id'   => '5333444555',
            'guest_contact_name'   => 'قرارداد گران',
            'guest_contact_mobile' => '09153334445',
            'nights'               => 2,
            'medical_contract_id'  => $second->id,
            'medical_tariff_id'    => $secondTariff->id,
        ]);

        $this->assertSame($first->id, (int) $cheap->medical_contract_id);
        $this->assertSame($second->id, (int) $expensive->medical_contract_id);
        $this->assertSame($first->program_employer_id, $cheap->program_employer_id);
        $this->assertSame($second->program_employer_id, $expensive->program_employer_id);
        $this->assertSame(4_000_000, (int) $cheap->total_price);
        $this->assertSame(18_000_000, (int) $expensive->total_price);
        $this->assertSame($first->contract_number, $cheap->medicalContractNumber());
        $this->assertSame($second->contract_number, $expensive->medicalContractNumber());
    }

    public function test_manual_booking_requires_contract_when_none_cover_the_stay(): void
    {
        $this->defaultContract()->update([
            'starts_on' => '2026-01-01',
            'ends_on'   => '2026-01-10',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', '2026-09-10', '2026-09-12', 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '5444555666')
            ->call('verifyBooker')
            ->set('guestContactName', 'بدون قرارداد')
            ->set('guestContactMobile', '09154445556')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_MEDICAL_ACCOMMODATION)
            ->assertSee('قرارداد فعالی وجود ندارد')
            ->call('nextStep')
            ->assertHasErrors(['medicalContractId']);
    }

    public function test_stay_extension_beyond_contract_end_is_rejected(): void
    {
        $contract = $this->defaultContract();
        $booking = $this->createMedicalBooking([
            'check_in'            => '2026-09-10',
            'nights'              => 4,
            'medical_contract_id' => $contract->id,
            'medical_tariff_id'   => $contract->tariffs()->ordered()->first()->id,
        ]);
        $contract->update(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-13']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('بازه قرارداد');

        app(BookingStayExtensionService::class)->extendCheckout($booking, '2026-09-15');
    }

    public function test_billing_active_contracts_filters_by_stay_dates(): void
    {
        $first = $this->defaultContract();
        $first->update(['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
        $second = $this->makeDatedContract($first->program_employer_id, '2026-10-01', '2026-10-31', 'مهر', 3_000_000);

        $billing = app(MedicalAccommodationBillingService::class);
        $september = $billing->activeContracts($this->accommodation, '2026-09-10', '2026-09-12');
        $october = $billing->activeContracts($this->accommodation, '2026-10-05', '2026-10-07');

        $this->assertSame([$first->id], $september->pluck('id')->all());
        $this->assertSame([$second->id], $october->pluck('id')->all());
    }

    public function test_covers_stay_allows_checkout_on_the_morning_after_contract_end(): void
    {
        $contract = $this->defaultContract();
        $contract->update(['starts_on' => '2026-09-10', 'ends_on' => '2026-09-12']);

        $this->assertTrue($contract->coversStay('2026-09-10', '2026-09-13'));
        $this->assertFalse($contract->coversStay('2026-09-10', '2026-09-14'));
        $this->assertFalse($contract->coversStay('2026-09-09', '2026-09-11'));
    }

    public function test_inactive_contract_is_hidden_from_manual_booking(): void
    {
        $first = $this->defaultContract();
        $second = $this->makeDatedContract($first->program_employer_id, null, null, 'غیرفعال', 5_000_000);
        $second->update(['is_active' => false]);

        [$checkIn, $checkOut] = $this->futureStay(2);

        Livewire::actingAs($this->adminUser)
            ->test(ManualBookingForm::class, $this->formParams())
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $this->roomType->id, $this->roomRate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '5555666777')
            ->call('verifyBooker')
            ->set('guestContactName', 'قرارداد فعال')
            ->set('guestContactMobile', '09155556667')
            ->call('nextStep')
            ->set('paymentMethod', Booking::PAYMENT_MEDICAL_ACCOMMODATION)
            ->assertSee($first->contract_number)
            ->assertDontSee($second->contract_number);
    }

    public function test_settings_can_save_distinct_dates_per_contract(): void
    {
        app(MedicalAccommodationProvisioner::class)->createContract($this->accommodation, [
            'program_employer_id' => $this->defaultContract()->program_employer_id,
            'seed_tariffs'        => true,
        ]);

        $start = Jalalian::fromCarbon(Carbon::parse('2026-09-01'))->format('Y/m/d');
        $end = Jalalian::fromCarbon(Carbon::parse('2026-09-30'))->format('Y/m/d');

        Livewire::actingAs($this->adminUser)
            ->test(AccommodationMedicalAccommodationSettings::class, [
                'accommodation' => $this->accommodation,
            ])
            ->set('contracts.0.starts_on_jalali', $start)
            ->set('contracts.0.ends_on_jalali', $end)
            ->call('saveContract', 0)
            ->assertHasNoErrors();

        $contract = $this->defaultContract()->fresh();
        $this->assertSame('2026-09-01', $contract->starts_on?->format('Y-m-d'));
        $this->assertSame('2026-09-30', $contract->ends_on?->format('Y-m-d'));
    }

    private function defaultContract(): MedicalAccommodationContract
    {
        return MedicalAccommodationContract::query()
            ->where('accommodation_id', $this->accommodation->id)
            ->orderBy('id')
            ->firstOrFail();
    }

    private function expectedNumber(int $sequence): string
    {
        $province = $this->accommodation->fresh()->resolvedProvince();
        $this->assertNotNull($province);

        return MedicalAccommodationContractNumbers::prefixForProvince($province)
            .str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    private function makeDatedContract(
        ?int $employerId,
        ?string $startsOn,
        ?string $endsOn,
        string $label,
        int $nightlyRate,
    ): MedicalAccommodationContract {
        $contract = app(MedicalAccommodationProvisioner::class)->createContract($this->accommodation, [
            'program_employer_id' => $employerId,
            'starts_on'           => $startsOn,
            'ends_on'             => $endsOn,
            'seed_tariffs'        => true,
        ]);

        $contract->tariffs()->update(['is_active' => false]);
        MedicalAccommodationTariff::create([
            'accommodation_id'       => $this->accommodation->id,
            'contract_id'            => $contract->id,
            'key'                    => 'custom_'.uniqid(),
            'label'                  => $label,
            'nightly_rate'           => $nightlyRate,
            'companion_nightly_rate' => 0,
            'companions_included'    => 0,
            'max_companions'         => 1,
            'sort_order'             => 1,
            'is_active'              => true,
        ]);

        return $contract->fresh(['tariffs']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function createMedicalBooking(array $overrides = []): Booking
    {
        $nights = (int) ($overrides['nights'] ?? 2);
        $checkIn = $overrides['check_in'] ?? now()->addDays(10)->format('Y-m-d');
        $checkOut = $overrides['check_out'] ?? Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');
        $this->guestSeq++;
        $nationalId = $overrides['booker_national_id'] ?? ('6111'.str_pad((string) $this->guestSeq, 6, '0', STR_PAD_LEFT));
        $mobile = $overrides['guest_contact_mobile'] ?? ('09161'.str_pad((string) $this->guestSeq, 6, '0', STR_PAD_LEFT));
        $name = $overrides['guest_contact_name'] ?? 'مهمان قرارداد';
        $tariffId = $overrides['medical_tariff_id'] ?? $this->defaultContract()->tariffs()->ordered()->first()->id;

        return app(ManualBookingService::class)->create($this->accommodation, [
            'room_lines' => [[
                'room_type_id'     => $this->roomType->id,
                'room_rate_id'     => $this->roomRate->id,
                'adults'           => 1,
                'children_under_6' => 0,
                'guests'           => 1,
                'extra_guests'     => 0,
                'bill_full_rooms'  => false,
            ]],
            'check_in'                 => $checkIn,
            'check_out'                => $checkOut,
            'guests'                   => 1,
            'booker_national_id'       => $nationalId,
            'payment_method'           => Booking::PAYMENT_MEDICAL_ACCOMMODATION,
            'is_medical_accommodation' => true,
            'medical_contract_id'      => $overrides['medical_contract_id'] ?? $this->defaultContract()->id,
            'medical_tariff_id'        => $tariffId,
            'guest_contact_name'       => $name,
            'guest_contact_mobile'     => $mobile,
            'guest_details'            => [[
                'full_name'   => $name,
                'national_id' => $nationalId,
                'mobile'      => $mobile,
            ]],
        ], $this->adminUser);
    }

    /** @return array{accommodation:\App\Models\Accommodation, panel:string} */
    private function formParams(): array
    {
        return [
            'accommodation' => $this->accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
            'panel'         => 'admin',
        ];
    }

    /** @return array{0:string,1:string} */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(10)->format('Y-m-d');
        $checkOut = Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');

        return [$checkIn, $checkOut];
    }
}
