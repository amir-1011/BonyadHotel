<?php

namespace Tests\Feature;

use App\Livewire\Admin\AccommodationPosMappingIndex;
use App\Livewire\ManualBookingForm;
use App\Models\AccommodationPosMapping;
use App\Models\Booking;
use App\Models\ProvincePosSettlement;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\PcPosAgentClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccommodationPosMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'ادمین پوز',
            'mobile' => '09120000010',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_can_create_pos_mapping(): void
    {
        $accommodation = $this->createTestAccommodation(['name' => 'اقامتگاه اصفهان']);

        $this->actingAs($this->admin)
            ->get(route('admin.pos-mappings.index'))
            ->assertOk();

        Livewire::actingAs($this->admin)
            ->test(AccommodationPosMappingIndex::class)
            ->set('formAccommodationId', (string) $accommodation->id)
            ->set('formWindowsLanIp', '100.64.12.20')
            ->set('formPosLanIp', '192.168.18.53')
            ->set('formPosPort', '1362')
            ->set('formAgentPort', '8088')
            ->call('save')
            ->assertHasNoErrors();

        $mapping = AccommodationPosMapping::query()->first();
        $this->assertNotNull($mapping);
        $this->assertSame($accommodation->id, $mapping->accommodation_id);
        $this->assertSame('100.64.12.20', $mapping->windows_lan_ip);
        $this->assertSame('192.168.18.53', $mapping->pos_lan_ip);
        $this->assertSame(1362, $mapping->pos_port);
        $this->assertSame(8088, $mapping->agent_port);
    }

    public function test_agent_client_posts_to_windows_ip_with_pos_ip_in_body(): void
    {
        Http::fake([
            'http://100.64.12.20:8088/pcpos' => Http::response([
                'resp_tlv' => [
                    'RS' => '00',
                    'RN' => '513671105286',
                    'TM' => '20077506',
                    'PN' => '627412******1673',
                    'AM' => '3010',
                    'TI' => '1404/08/11-10:54:13',
                ],
                'resp_code' => 0,
                'resp_msg' => 'Successful',
            ], 200),
        ]);

        $result = app(PcPosAgentClient::class)->purchase(
            '100.64.12.20',
            '192.168.18.53',
            3010,
            1362,
            8088,
        );

        $this->assertTrue($result->approved());
        $this->assertSame('00', $result->rs());
        $this->assertSame('1673', $result->cardLastFour());
        $this->assertSame('513671105286', $result->rrn());

        Http::assertSent(function ($request) {
            return $request->url() === 'http://100.64.12.20:8088/pcpos'
                && $request['POSIP'] === '192.168.18.53'
                && $request['POSPORT'] === '1362'
                && $request['AM'] === '3010'
                && $request['ConnectionType'] === 'TCP';
        });
    }

    public function test_manual_booking_is_created_only_when_pos_approves(): void
    {
        $accommodation = $this->createTestAccommodation();
        $this->seedStay($accommodation);

        AccommodationPosMapping::create([
            'accommodation_id' => $accommodation->id,
            'windows_lan_ip' => '100.64.12.20',
            'pos_lan_ip' => '192.168.18.53',
            'pos_port' => 1362,
            'agent_port' => 8088,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);
        $this->seedPosSettlement($accommodation);

        Http::fake([
            'http://100.64.12.20:8088/pcpos' => Http::response([
                'resp_tlv' => [
                    'RS' => '00',
                    'RN' => '999888777666',
                    'TR' => '101133',
                    'TM' => '20077506',
                    'PN' => '627412******1673',
                    'TI' => '1404/08/11-10:54:13',
                ],
                'resp_code' => 0,
                'resp_msg' => 'Successful',
            ], 200),
        ]);

        $component = $this->filledManualBooking($accommodation)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertNotNull($component->get('createdBookingId'));
        $booking = Booking::find($component->get('createdBookingId'));
        $this->assertNotNull($booking);
        $this->assertSame(1, $booking->paymentRecords()->count());
        $record = $booking->paymentRecords()->first();
        $this->assertSame('1673', $record->card_last_four);
        $this->assertSame('999888777666', $record->transaction_tracking);
        $this->assertSame('00', $record->pos_response['resp_tlv']['RS'] ?? null);
        $this->assertNotEmpty($record->pos_response['shares'] ?? []);
        $shareSum = collect($record->pos_response['shares'])->sum(fn ($share) => (int) $share['amount']);
        $this->assertSame((int) $booking->total_price, $shareSum);

        Http::assertSent(function ($request) {
            $shares = $request['Shares'] ?? [];
            if ($shares === []) {
                return false;
            }
            $sum = array_sum(array_map(fn ($share) => (int) $share['amount'], $shares));

            return $request['AM'] === (string) $sum
                && isset($shares[0]['iban'], $shares[0]['trackingId']);
        });
    }

    public function test_manual_booking_is_not_created_when_settlement_is_missing(): void
    {
        $accommodation = $this->createTestAccommodation();
        $this->seedStay($accommodation);

        AccommodationPosMapping::create([
            'accommodation_id' => $accommodation->id,
            'windows_lan_ip' => '100.64.12.20',
            'pos_lan_ip' => '192.168.18.53',
            'pos_port' => 1362,
            'agent_port' => 8088,
            'is_active' => true,
        ]);

        Http::fake();

        $this->filledManualBooking($accommodation)
            ->call('submit')
            ->assertSet('createdBookingId', null);

        $this->assertSame(0, Booking::query()->count());
        Http::assertNothingSent();
    }

    public function test_manual_booking_is_not_created_when_pos_declines(): void
    {
        $accommodation = $this->createTestAccommodation();
        $this->seedStay($accommodation);

        AccommodationPosMapping::create([
            'accommodation_id' => $accommodation->id,
            'windows_lan_ip' => '100.64.12.20',
            'pos_lan_ip' => '192.168.18.53',
            'pos_port' => 1362,
            'agent_port' => 8088,
            'is_active' => true,
        ]);
        $this->seedPosSettlement($accommodation);

        Http::fake([
            'http://100.64.12.20:8088/pcpos' => Http::response([
                'resp_tlv' => [
                    'RS' => '55',
                    'AM' => '1000000',
                    'TI' => '1404/08/11-11:02:45',
                ],
                'resp_code' => 0,
                'resp_msg' => 'Failed',
            ], 200),
        ]);

        $this->filledManualBooking($accommodation)
            ->call('submit')
            ->assertSet('createdBookingId', null);

        $this->assertSame(0, Booking::query()->count());
    }

    private function seedStay($accommodation): RoomType
    {
        $roomType = RoomType::create([
            'accommodation_id' => $accommodation->id,
            'name' => 'اتاق تست پوز',
            'capacity' => 2,
            'room_count' => 2,
            'is_active' => true,
        ]);
        RoomRate::create([
            'room_type_id' => $roomType->id,
            'name' => 'نرخ استاندارد',
            'price_per_night' => 1_000_000,
            'is_active' => true,
        ]);

        return $roomType;
    }

    private function seedPosSettlement($accommodation): ProvincePosSettlement
    {
        $province = $accommodation->fresh(['city.province', 'county.province'])->resolvedProvince();
        $settlement = ProvincePosSettlement::create([
            'province_id' => $province->id,
            'service_fee_label' => 'حق سرویس',
            'service_fee_iban' => 'IR360550010200200165000001',
            'is_active' => true,
        ]);
        $settlement->accounts()->create([
            'label' => 'حساب اول',
            'iban' => 'IR090550010200200165000002',
            'percentage' => 70,
            'sort_order' => 0,
        ]);
        $settlement->accounts()->create([
            'label' => 'حساب دوم',
            'iban' => 'IR170550010200200165000003',
            'percentage' => 30,
            'sort_order' => 1,
        ]);

        return $settlement;
    }

    private function filledManualBooking($accommodation)
    {
        $roomType = $accommodation->roomTypes()->first();
        $rate = $roomType->rates()->first();
        [$checkIn, $checkOut] = $this->futureStay(1);

        return Livewire::actingAs($this->admin)
            ->test(ManualBookingForm::class, [
                'accommodation' => $accommodation->fresh(['roomTypes.rates', 'roomTypes.rooms', 'city']),
                'panel' => 'admin',
            ])
            ->call('commitRoomFromDrawer', $checkIn, $checkOut, 1, $roomType->id, $rate->id, 0, false, 0, 1)
            ->call('nextStep')
            ->set('bookerNationalId', '4440123456')
            ->call('verifyBooker')
            ->set('guestContactName', 'مهمان پوز')
            ->set('guestContactMobile', '09121112233')
            ->call('nextStep')
            ->set('paymentMethod', 'card_terminal');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function futureStay(int $nights): array
    {
        $checkIn = now()->addDays(3)->toDateString();
        $checkOut = now()->addDays(3 + $nights)->toDateString();

        return [$checkIn, $checkOut];
    }
}
