<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Program;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\ProgramBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramBookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $room;
    private User $hostUser;
    private ProgramEmployer $employer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'guest', 'guard_name' => 'web']);

        $this->accommodation = $this->createTestAccommodation();
        $this->roomType = RoomType::create([
            'accommodation_id' => $this->accommodation->id,
            'name'             => 'اتاق اردو',
            'capacity'         => 4,
            'room_count'       => 3,
            'is_active'        => true,
        ]);
        $this->roomRate = RoomRate::create([
            'room_type_id'    => $this->roomType->id,
            'name'            => 'نرخ اردو',
            'price_per_night' => 500_000,
            'is_active'       => true,
        ]);
        $this->room = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۱',
            'is_active'    => true,
        ]);

        $this->hostUser = User::create([
            'name'   => 'میزبان تست',
            'mobile' => '09120000001',
        ]);
        $this->hostUser->assignRole('host');
        $this->accommodation->hosts()->attach($this->hostUser->id);

        $this->employer = ProgramEmployer::create([
            'name'                    => 'کارفرمای تست',
            'employer_code'           => 'EMP-TEST-01',
            'national_or_economic_id' => '5566778899',
            'mobile'                  => '09125556677',
        ]);
    }

    public function test_program_booking_service_creates_linked_booking_and_program(): void
    {
        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $catalog = ServiceCatalog::create([
            'accommodation_id' => $this->accommodation->id,
            'key'              => 'breakfast',
            'name'             => 'صبحانه',
            'default_price'    => 100_000,
            'is_active'        => true,
        ]);

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'           => 'اردوی تست',
                'description'     => 'توضیح تست',
                'program_type'    => Program::TYPE_CAMP,
                'program_employer_id' => $this->employer->id,
                'contractor'      => 'پیمانکار تست',
                'guest_count'     => 20,
                'rooms_allocated' => 1,
                'check_in'        => $checkIn,
                'check_out'       => $checkOut,
                'room_lines'      => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                    'room_name'    => $this->room->name,
                ]],
                'services' => [[
                    'service_catalog_id' => $catalog->id,
                    'name'               => 'صبحانه',
                    'unit_price'         => 100_000,
                    'quantity'           => 20,
                ]],
                'payment_type'    => Program::PAYMENT_CASH,
                'base_price'      => 2_000_000,
                'discount_amount' => 200_000,
                'deposit_amount'  => 1_000_000,
            ],
            $this->hostUser,
        );

        $this->assertInstanceOf(Program::class, $program);
        $this->assertNotNull($program->booking_id);

        $booking = Booking::find($program->booking_id);
        $this->assertNotNull($booking);
        $this->assertSame('program', $booking->booking_source);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($checkIn, $booking->check_in->toDateString());
        $this->assertSame($checkOut, $booking->check_out->toDateString());
        $this->assertSame(20, $booking->guests);
        $this->assertSame(1, $booking->bookingRooms()->count());
        $this->assertSame($this->room->id, $booking->bookingRooms()->first()->room_id);
        $this->assertSame(1, $booking->services()->count());

        $this->assertSame(2_000_000, $program->base_price);
        $this->assertSame(2_000_000, $program->services_subtotal);
        $this->assertSame(3_800_000, $program->total_amount);
        $this->assertSame(2_800_000, $program->remainingAmount());
    }

    public function test_booking_filter_includes_program_source(): void
    {
        $checkIn = Carbon::today()->addDays(10)->toDateString();
        $checkOut = Carbon::today()->addDays(12)->toDateString();

        app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'        => 'رویداد فیلتر',
                'program_type' => Program::TYPE_EVENT,
                'program_employer_id' => $this->employer->id,
                'guest_count'  => 5,
                'rooms_allocated' => 1,
                'check_in'     => $checkIn,
                'check_out'    => $checkOut,
                'room_lines'   => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                ]],
                'payment_type' => Program::PAYMENT_SUPPORTIVE,
                'base_price'   => 1_000_000,
            ],
            $this->hostUser,
        );

        $filtered = Booking::query()
            ->where('booking_source', 'program')
            ->whereHas('program', fn ($q) => $q->where('payment_type', Program::PAYMENT_SUPPORTIVE))
            ->count();

        $this->assertSame(1, $filtered);
    }

    public function test_program_booking_service_persists_guest_details(): void
    {
        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $room2 = Room::create([
            'room_type_id' => $this->roomType->id,
            'name'         => '۱۰۲',
            'is_active'    => true,
        ]);

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'           => 'اردوی مهمان',
                'program_type'    => Program::TYPE_CAMP,
                'program_employer_id' => $this->employer->id,
                'guest_count'     => 3,
                'rooms_allocated' => 2,
                'check_in'        => $checkIn,
                'check_out'       => $checkOut,
                'room_lines'      => [
                    [
                        'room_type_id' => $this->roomType->id,
                        'room_rate_id' => $this->roomRate->id,
                        'room_id'      => $this->room->id,
                        'room_name'    => $this->room->name,
                    ],
                    [
                        'room_type_id' => $this->roomType->id,
                        'room_rate_id' => $this->roomRate->id,
                        'room_id'      => $room2->id,
                        'room_name'    => $room2->name,
                    ],
                ],
                'payment_type' => Program::PAYMENT_CASH,
                'base_price'   => 1_000_000,
                'guest_details' => [
                    [
                        'full_name'       => 'علی رضایی',
                        'national_id'     => '1234567890',
                        'mobile'          => '09121111111',
                        'relation'        => '',
                        'room_line_index' => 0,
                        'sort_order'      => 0,
                    ],
                    [
                        'full_name'       => 'مریم احمدی',
                        'national_id'     => '0987654321',
                        'mobile'          => '09122222222',
                        'relation'        => 'همسر',
                        'room_line_index' => 0,
                        'sort_order'      => 1,
                    ],
                    [
                        'full_name'       => 'رضا کریمی',
                        'national_id'     => '1122334455',
                        'mobile'          => '09123333333',
                        'relation'        => 'فرزند',
                        'room_line_index' => 1,
                        'sort_order'      => 2,
                    ],
                ],
            ],
            $this->hostUser,
        );

        $booking = Booking::find($program->booking_id);
        $this->assertNotNull($booking);
        $this->assertSame(3, $booking->guestDetails()->count());

        $mainGuest = $booking->guestDetails()->where('sort_order', 0)->first();
        $this->assertSame('علی رضایی', $mainGuest->full_name);
        $this->assertSame(\App\Models\BookingGuestDetail::RELATION_MAIN_GUEST, $mainGuest->relation);
        $this->assertSame($this->room->id, $mainGuest->bookingRoom?->room_id);

        $thirdGuest = $booking->guestDetails()->where('sort_order', 2)->first();
        $this->assertSame('رضا کریمی', $thirdGuest->full_name);
        $this->assertSame($room2->id, $thirdGuest->bookingRoom?->room_id);
    }

    public function test_program_booking_service_stores_guest_list_spreadsheet(): void
    {
        Storage::fake('public');

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $guestListFile = UploadedFile::fake()->create('guests.xlsx', 50, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'                => 'اردوی لیست مهمان',
                'program_type'         => Program::TYPE_CAMP,
                'program_employer_id'  => $this->employer->id,
                'guest_count'          => 5,
                'rooms_allocated'      => 1,
                'check_in'             => $checkIn,
                'check_out'            => $checkOut,
                'room_lines'           => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                    'room_name'    => $this->room->name,
                ]],
                'payment_type'         => Program::PAYMENT_CASH,
                'base_price'           => 1_000_000,
                'guest_list_documents' => [$guestListFile],
            ],
            $this->hostUser,
        );

        $program->refresh();

        $this->assertNotEmpty($program->guest_list_documents);
        $this->assertCount(1, $program->guest_list_documents);
        Storage::disk('public')->assertExists($program->guest_list_documents[0]);
        $this->assertStringEndsWith('.xlsx', $program->guest_list_documents[0]);
    }

    public function test_program_document_service_accepts_pdf_extension(): void
    {
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $rules = \App\Services\ProgramDocumentService::fileRules();

        $validator = validator(['doc' => $file], ['doc' => $rules]);

        $this->assertFalse($validator->fails(), implode(', ', $validator->errors()->all()));
    }

    public function test_program_booking_service_stores_payment_and_beneficiary_documents(): void
    {
        Storage::fake('public');

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $beneficiary = \App\Models\ProgramBeneficiary::create([
            'name'                    => 'ذینفع تست',
            'beneficiary_code'        => 'BEN-001',
            'national_or_economic_id' => '1234567890',
            'mobile'                  => '09120000099',
        ]);

        $paymentFile = UploadedFile::fake()->create('payment.pdf', 100, 'application/pdf');
        $beneficiaryFile = UploadedFile::fake()->create('beneficiary.pdf', 100, 'application/pdf');

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            [
                'title'             => 'اردوی مدارک',
                'program_type'      => Program::TYPE_CAMP,
                'program_employer_id' => $this->employer->id,
                'guest_count'       => 1,
                'rooms_allocated'   => 1,
                'check_in'          => $checkIn,
                'check_out'         => $checkOut,
                'room_lines'        => [[
                    'room_type_id' => $this->roomType->id,
                    'room_rate_id' => $this->roomRate->id,
                    'room_id'      => $this->room->id,
                    'room_name'    => $this->room->name,
                ]],
                'payment_type'      => Program::PAYMENT_CREDIT,
                'payment_documents' => [$paymentFile],
                'base_price'        => 1_000_000,
                'beneficiary_costs' => [[
                    'program_beneficiary_id' => $beneficiary->id,
                    'debt_amount'            => 500_000,
                    'description'            => 'بدهی تست',
                    'documents'              => [$beneficiaryFile],
                ]],
            ],
            $this->hostUser,
        );

        $program->refresh()->load('beneficiaryCosts');

        $this->assertNotEmpty($program->payment_documents);
        $this->assertCount(1, $program->payment_documents);
        Storage::disk('public')->assertExists($program->payment_documents[0]);

        $cost = $program->beneficiaryCosts->first();
        $this->assertNotNull($cost);
        $this->assertNotEmpty($cost->documents);
        Storage::disk('public')->assertExists($cost->documents[0]);
    }
}
