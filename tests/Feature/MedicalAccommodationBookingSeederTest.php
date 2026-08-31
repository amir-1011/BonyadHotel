<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Support\MedicalAccommodationTariffs;
use Database\Seeders\MedicalAccommodationBookingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MedicalAccommodationBookingSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $farsId = $this->ensureTestProvinceId('فارس', '507');
        $mazandaranId = $this->ensureTestProvinceId('مازندران', '521');
        $tehranId = $this->ensureTestProvinceId('تهران', '508');

        $this->createTestAccommodation([
            'name'    => 'هتل شیراز درمانی',
            'city_id' => $this->ensureTestCityId($farsId, 'شیراز'),
        ]);
        $this->createTestAccommodation([
            'name'    => 'هتل مرودشت درمانی',
            'city_id' => $this->ensureTestCityId($farsId, 'مرودشت'),
        ]);
        $this->createTestAccommodation([
            'name'    => 'هتل ساری درمانی',
            'city_id' => $this->ensureTestCityId($mazandaranId, 'ساری'),
        ]);
        $this->createTestAccommodation([
            'name'    => 'هتل تهران درمانی',
            'city_id' => $this->ensureTestCityId($tehranId, 'تهران'),
        ]);
    }

    public function test_seeds_one_hundred_medical_bookings_covering_all_states(): void
    {
        $this->seed(MedicalAccommodationBookingSeeder::class);

        $bookings = $this->seededBookings();

        $this->assertCount(MedicalAccommodationBookingSeeder::COUNT, $bookings);
        $this->assertTrue($bookings->every(fn (Booking $booking) => $booking->is_medical_accommodation));
        $this->assertTrue($bookings->every(fn (Booking $booking) => $booking->payment_method === Booking::PAYMENT_MEDICAL_ACCOMMODATION));
        $this->assertTrue($bookings->every(fn (Booking $booking) => (int) $booking->total_price === 0));
        $this->assertTrue($bookings->every(fn (Booking $booking) => (int) $booking->nights >= 1));

        $this->assertContains('confirmed', $bookings->pluck('status')->all());
        $this->assertContains('pending', $bookings->pluck('status')->all());
        $this->assertContains('cancelled', $bookings->pluck('status')->all());
        $this->assertContains('manual', $bookings->pluck('booking_source')->all());
        $this->assertContains('online', $bookings->pluck('booking_source')->all());

        $this->assertGreaterThanOrEqual(3, $bookings->pluck('accommodation.city.province_id')->unique()->count());
        $this->assertContains(1, $bookings->pluck('nights')->all());
        $this->assertContains(7, $bookings->pluck('nights')->all());
        $this->assertContains(0, $bookings->pluck('medical_companion_count')->all());
        $this->assertTrue($bookings->contains(fn (Booking $booking) => (int) $booking->medical_companion_count > 0));
        $this->assertTrue($bookings->contains(fn (Booking $booking) => $booking->medical_contract_id === null));
        $this->assertTrue($bookings->contains(fn (Booking $booking) => $booking->medical_tariff_id === null));
        $this->assertTrue($bookings->contains(fn (Booking $booking) => $booking->program_employer_id === null));
        $this->assertTrue($bookings->contains(fn (Booking $booking) => (int) $booking->employer_debt_amount > 0));

        $keys = $bookings
            ->map(fn (Booking $booking) => $booking->medicalTariff?->key ?? $booking->medical_tariff_snapshot['key'] ?? null)
            ->filter()
            ->unique()
            ->values();

        $this->assertContains(MedicalAccommodationTariffs::KEY_NECK_INJURY, $keys->all());
        $this->assertContains(MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE, $keys->all());
        $this->assertContains(MedicalAccommodationTariffs::KEY_OTHER_VETERAN, $keys->all());
        $this->assertContains(MedicalAccommodationTariffs::KEY_MEDICAL_STAFF, $keys->all());
        $this->assertContains(MedicalAccommodationTariffs::KEY_NORMAL_HOST, $keys->all());

        $now = Jalalian::now();
        $checkIns = $bookings->map(fn (Booking $booking) => Jalalian::fromCarbon($booking->check_in));
        $monthKeys = $checkIns->map(fn (Jalalian $date) => $date->getYear().'-'.$date->getMonth())->unique();

        $this->assertTrue($checkIns->contains(
            fn (Jalalian $date) => $date->getYear() === $now->getYear() && $date->getMonth() === $now->getMonth()
        ));
        $this->assertTrue($checkIns->contains(fn (Jalalian $date) => $date->getYear() === $now->getYear() - 1));
        $this->assertTrue($checkIns->contains(fn (Jalalian $date) => $date->toCarbon()->gt(now())));
        $this->assertGreaterThanOrEqual(4, $monthKeys->count());

        $this->seed(MedicalAccommodationBookingSeeder::class);

        $this->assertCount(
            MedicalAccommodationBookingSeeder::COUNT,
            $this->seededBookings(),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    private function seededBookings()
    {
        return Booking::query()
            ->medicalAccommodation()
            ->with(['accommodation.city.province', 'medicalTariff'])
            ->where('tracking_code', 'like', MedicalAccommodationBookingSeeder::TRACKING_PREFIX.'%')
            ->orderBy('tracking_code')
            ->get();
    }
}
