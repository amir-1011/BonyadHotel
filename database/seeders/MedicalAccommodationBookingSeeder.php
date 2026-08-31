<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\MedicalAccommodationContract;
use App\Models\MedicalAccommodationTariff;
use App\Models\User;
use App\Services\MedicalAccommodationPricingService;
use App\Services\MedicalAccommodationProvisioner;
use App\Support\MedicalAccommodationTariffs;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Role;

class MedicalAccommodationBookingSeeder extends Seeder
{
    public const TRACKING_PREFIX = 'MS';

    public const COUNT = 100;

    /** @var list<string> */
    private const GUEST_NAMES = [
        'رضا کاظمی', 'مریم احمدی', 'حسن جعفری', 'زهرا محمدی', 'امیر حسینی',
        'نرگس رضایی', 'کاوه نوری', 'لیلا کریمی', 'سعید موسوی', 'فاطمه صادقی',
        'مجید اکبری', 'شیدا مرادی', 'بهرام قاسمی', 'مینا شریفی', 'فرهاد یوسفی',
        'پریسا نعمتی', 'علیرضا تهرانی', 'سارا اصفهانی', 'حامد شیرازی', 'الهام تبریزی',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasTable('medical_accommodation_contracts')) {
            $this->command?->warn('جداول اسکان درمانی آماده نیست. ابتدا migration را اجرا کنید.');

            return;
        }

        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $accommodations = $this->loadAccommodations();
        if ($accommodations->isEmpty()) {
            $this->command?->warn('اقامتگاهی با شهر و استان یافت نشد. ابتدا اقامتگاه‌ها را بسازید.');

            return;
        }

        $guests = $this->ensureGuests();
        $adminId = $this->adminUserId();
        $pricing = app(MedicalAccommodationPricingService::class);
        $buckets = $accommodations->groupBy(fn (Accommodation $acc) => (int) $acc->city->province_id)->values();

        $created = 0;
        $updated = 0;
        $provinceIds = [];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $scenario = $this->scenario($i);
            $accommodation = $this->pickAccommodation($buckets, $i);
            $guest = $guests[($i - 1) % $guests->count()];
            $contract = $scenario['omit_contract']
                ? null
                : $accommodation->medicalAccommodationContracts->sortBy('id')->first();
            $tariff = $scenario['omit_tariff']
                ? null
                : $this->pickTariff($accommodation, $contract, $scenario['tariff_key'], $i);

            $nights = max(1, (int) $scenario['nights']);
            [$checkIn, $checkOut] = $this->stayDates($scenario['period'], $nights, $i);
            $companions = $this->companionCount($tariff, $scenario['companion'], $i);
            $guestsCount = $companions + 1;
            [$debt, $snapshot] = $this->quote($pricing, $tariff, $contract, $nights, $companions);
            $employerId = $scenario['omit_employer']
                ? null
                : ($contract?->program_employer_id ?: $accommodation->medicalAccommodationSetting?->program_employer_id);

            $tracking = sprintf('%s%06d', self::TRACKING_PREFIX, $i);
            $payload = [
                'user_id'                  => $guest->id,
                'created_by'               => $adminId,
                'accommodation_id'         => $accommodation->id,
                'check_in'                 => $checkIn,
                'check_out'                => $checkOut,
                'guests'                   => $guestsCount,
                'rooms_consumed'           => 1,
                'extra_guests'             => 0,
                'nights'                   => $nights,
                'base_price'               => 0,
                'discount_percentage'      => 0,
                'discount_amount'          => 0,
                'total_price'              => 0,
                'status'                   => $scenario['status'],
                'booking_source'           => $scenario['source'],
                'payment_method'           => Booking::PAYMENT_MEDICAL_ACCOMMODATION,
                'is_medical_accommodation' => true,
                'medical_contract_id'      => $contract?->id,
                'medical_tariff_id'        => $tariff?->id,
                'medical_tariff_snapshot'  => $snapshot,
                'program_employer_id'      => $employerId,
                'employer_debt_amount'     => $debt,
                'medical_companion_count'  => $companions,
                'guest_contact_name'       => self::GUEST_NAMES[($i - 1) % count(self::GUEST_NAMES)],
                'guest_contact_mobile'     => $guest->mobile,
                'notes'                    => 'seed:medical-accommodation',
            ];

            $booking = Booking::query()->firstOrNew(['tracking_code' => $tracking]);
            $wasNew = ! $booking->exists;
            $booking->fill($payload);
            $booking->save();
            $wasNew ? $created++ : $updated++;

            $provinceIds[(int) $accommodation->city->province_id] = true;
        }

        $this->command?->info(sprintf(
            'رزرو اسکان درمانی نمونه: %d جدید، %d به‌روز، در %d استان.',
            $created,
            $updated,
            count($provinceIds),
        ));
    }

    /**
     * @return Collection<int, Accommodation>
     */
    private function loadAccommodations(): Collection
    {
        $provisioner = app(MedicalAccommodationProvisioner::class);

        $accommodations = Accommodation::query()
            ->with([
                'city.province',
                'medicalAccommodationSetting',
                'medicalAccommodationTariffs',
                'medicalAccommodationContracts.employer',
                'medicalAccommodationContracts.tariffs',
            ])
            ->where('is_active', true)
            ->whereHas('city.province')
            ->orderBy('id')
            ->get();

        return $accommodations
            ->map(function (Accommodation $accommodation) use ($provisioner) {
                if ($accommodation->medicalAccommodationContracts->isEmpty()) {
                    $provisioner->seedForAccommodation($accommodation);
                    $accommodation->load([
                        'medicalAccommodationSetting',
                        'medicalAccommodationTariffs',
                        'medicalAccommodationContracts.employer',
                        'medicalAccommodationContracts.tariffs',
                    ]);
                }

                return $accommodation;
            })
            ->filter(fn (Accommodation $acc) => $acc->city?->province_id)
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function ensureGuests(): Collection
    {
        $guests = collect();

        for ($i = 1; $i <= 20; $i++) {
            $user = User::query()->firstOrCreate(
                ['mobile' => sprintf('09128%06d', $i)],
                [
                    'name'               => self::GUEST_NAMES[($i - 1) % count(self::GUEST_NAMES)],
                    'mobile_verified_at' => now(),
                ],
            );
            if (! $user->hasRole('guest')) {
                $user->assignRole('guest');
            }
            $guests->push($user);
        }

        return $guests->values();
    }

    private function adminUserId(): ?int
    {
        $exists = Role::query()
            ->where('name', 'super_admin')
            ->where('guard_name', 'web')
            ->exists();

        if (! $exists) {
            return null;
        }

        return User::query()->role('super_admin')->value('id');
    }

    /**
     * @param  Collection<int, Collection<int, Accommodation>>  $buckets
     */
    private function pickAccommodation(Collection $buckets, int $index): Accommodation
    {
        $bucket = $buckets[($index - 1) % $buckets->count()];

        return $bucket[($index - 1) % $bucket->count()];
    }

    /**
     * @return array{
     *   status:string, period:string, nights:int, companion:string,
     *   tariff_key:?string, omit_contract:bool, omit_tariff:bool,
     *   omit_employer:bool, source:string
     * }
     */
    private function scenario(int $i): array
    {
        $defaults = [
            'status'        => 'confirmed',
            'period'        => 'current',
            'nights'        => 2,
            'companion'     => 'mix',
            'tariff_key'    => null,
            'omit_contract' => false,
            'omit_tariff'   => false,
            'omit_employer' => false,
            'source'        => 'manual',
        ];

        $scripted = match ($i) {
            1 => ['tariff_key' => MedicalAccommodationTariffs::KEY_NECK_INJURY, 'companion' => 'max', 'nights' => 3],
            2 => ['tariff_key' => MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE, 'companion' => 'max'],
            3 => ['tariff_key' => MedicalAccommodationTariffs::KEY_OTHER_VETERAN, 'companion' => 'billed', 'nights' => 4],
            4 => ['tariff_key' => MedicalAccommodationTariffs::KEY_OTHER_VETERAN, 'companion' => 'zero'],
            5 => ['status' => 'pending', 'tariff_key' => MedicalAccommodationTariffs::KEY_NECK_INJURY],
            6 => ['status' => 'cancelled', 'tariff_key' => MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE],
            7 => ['period' => 'previous_month', 'nights' => 3],
            8 => ['period' => 'previous_year', 'nights' => 5],
            9 => ['period' => 'future', 'status' => 'pending', 'nights' => 2],
            10 => ['period' => 'other_month', 'nights' => 2],
            11 => ['omit_contract' => true, 'period' => 'current'],
            12 => ['omit_tariff' => true, 'period' => 'current'],
            13 => ['omit_employer' => true, 'period' => 'current'],
            14 => ['nights' => 1, 'companion' => 'zero'],
            15 => ['nights' => 7, 'companion' => 'max', 'tariff_key' => MedicalAccommodationTariffs::KEY_NECK_INJURY],
            16 => ['tariff_key' => MedicalAccommodationTariffs::KEY_MEDICAL_STAFF, 'nights' => 2],
            17 => ['status' => 'pending', 'period' => 'previous_month'],
            18 => ['status' => 'cancelled', 'period' => 'previous_year'],
            19 => ['period' => 'span_months', 'nights' => 3],
            20 => ['source' => 'online', 'period' => 'current', 'nights' => 2],
            21 => ['tariff_key' => MedicalAccommodationTariffs::KEY_NORMAL_HOST, 'nights' => 2, 'companion' => 'max'],
            default => null,
        };

        if ($scripted !== null) {
            return array_merge($defaults, $scripted);
        }

        $periods = ['current', 'previous_month', 'other_month', 'previous_year', 'future'];
        $keys = [
            MedicalAccommodationTariffs::KEY_NECK_INJURY,
            MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE,
            MedicalAccommodationTariffs::KEY_OTHER_VETERAN,
            MedicalAccommodationTariffs::KEY_MEDICAL_STAFF,
            MedicalAccommodationTariffs::KEY_NORMAL_HOST,
        ];
        $status = $i % 11 === 0 ? 'cancelled' : ($i % 8 === 0 ? 'pending' : 'confirmed');

        return array_merge($defaults, [
            'status'     => $status,
            'period'     => $i % 17 === 0 ? 'span_months' : $periods[$i % 5],
            'nights'     => 1 + ($i % 6),
            'companion'  => ['zero', 'max', 'mix', 'billed'][$i % 4],
            'tariff_key' => $keys[$i % 5],
            'source'     => $i % 9 === 0 ? 'online' : 'manual',
        ]);
    }

    private function pickTariff(
        Accommodation $accommodation,
        ?MedicalAccommodationContract $contract,
        ?string $preferredKey,
        int $index,
    ): ?MedicalAccommodationTariff {
        $pool = $contract?->tariffs ?? $accommodation->medicalAccommodationTariffs;
        $pool = $pool instanceof Collection ? $pool : collect($pool);

        if ($preferredKey) {
            $match = $pool->firstWhere('key', $preferredKey)
                ?? $accommodation->medicalAccommodationTariffs->firstWhere('key', $preferredKey);
            if ($match) {
                return $match;
            }
        }

        $active = $pool->where('is_active', true)->values();
        if ($active->isEmpty()) {
            $active = MedicalAccommodationTariff::query()
                ->where('accommodation_id', $accommodation->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        if ($active->isEmpty()) {
            return $pool->first();
        }

        return $active[($index - 1) % $active->count()];
    }

    private function companionCount(?MedicalAccommodationTariff $tariff, string $mode, int $index): int
    {
        $max = max(0, (int) ($tariff?->max_companions ?? 1));
        $included = max(0, (int) ($tariff?->companions_included ?? 0));

        return match ($mode) {
            'zero' => 0,
            'max' => $max,
            'billed' => min($max, $included + 1),
            default => $max > 0 ? $index % ($max + 1) : 0,
        };
    }

    /**
     * @return array{0:int, 1:?array<string, mixed>}
     */
    private function quote(
        MedicalAccommodationPricingService $pricing,
        ?MedicalAccommodationTariff $tariff,
        ?MedicalAccommodationContract $contract,
        int $nights,
        int $companions,
    ): array {
        if (! $tariff) {
            return [1_500_000 * $nights, null];
        }

        $tariff->loadMissing('contract');
        $snapshot = $tariff->toQuoteSnapshot();

        if ((int) $snapshot['nightly_rate'] === 0) {
            $snapshot['nightly_rate'] = match ($tariff->key) {
                MedicalAccommodationTariffs::KEY_MEDICAL_STAFF => 2_800_000,
                MedicalAccommodationTariffs::KEY_NORMAL_HOST => 2_200_000,
                default => 2_000_000,
            };
            if ((int) $snapshot['companion_nightly_rate'] === 0 && (int) $snapshot['max_companions'] > (int) $snapshot['companions_included']) {
                $snapshot['companion_nightly_rate'] = 800_000;
            }
        }

        $companions = min($companions, max(0, (int) $snapshot['max_companions']));
        $quote = $pricing->quote($snapshot, $nights, $companions);

        if ($contract && empty($snapshot['contract_number'])) {
            $snapshot['contract_number'] = $contract->contract_number;
            $snapshot['contract_id'] = $contract->id;
        }

        return [(int) $quote['stay_total'], $snapshot];
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function stayDates(string $period, int $nights, int $salt): array
    {
        $now = Jalalian::now();
        $year = $now->getYear();
        $month = $now->getMonth();

        if ($period === 'previous_year') {
            [$year, $month] = [$year - 1, 1 + ($salt % 12)];
        } elseif ($period === 'previous_month') {
            [$year, $month] = $this->shiftMonth($year, $month, -1);
        } elseif ($period === 'other_month') {
            [$year, $month] = $this->shiftMonth($year, $month, -2 - ($salt % 3));
        } elseif ($period === 'future') {
            [$year, $month] = $this->shiftMonth($year, $month, 1);
        }

        $monthStart = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/01', $year, $month));
        $days = $monthStart->getMonthDays();
        $maxStart = max(1, $days - $nights);
        $day = $period === 'span_months'
            ? max(1, $days - 1)
            : 1 + (($salt * 3) % $maxStart);

        $checkIn = Jalalian::fromFormat('Y/m/d', sprintf('%d/%02d/%02d', $year, $month, min($day, $days)))
            ->toCarbon()
            ->startOfDay();

        return [
            $checkIn->toDateString(),
            $checkIn->copy()->addDays($nights)->toDateString(),
        ];
    }

    /**
     * @return array{0:int, 1:int}
     */
    private function shiftMonth(int $year, int $month, int $delta): array
    {
        $month += $delta;
        while ($month < 1) {
            $month += 12;
            $year--;
        }
        while ($month > 12) {
            $month -= 12;
            $year++;
        }

        return [$year, $month];
    }
}
