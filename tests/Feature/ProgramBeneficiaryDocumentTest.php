<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProgramShow;
use App\Livewire\Host\ProgramShow as HostProgramShow;
use App\Livewire\ProgramBookingForm;
use App\Models\Program;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramBeneficiaryCost;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\ProgramBookingService;
use App\Support\ProgramDocumentPaths;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgramBeneficiaryDocumentTest extends TestCase
{
    use RefreshDatabase;

    private $accommodation;
    private RoomType $roomType;
    private RoomRate $roomRate;
    private Room $room;
    private User $hostUser;
    private ProgramEmployer $employer;
    private ProgramBeneficiary $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'host', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
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

        $provinceId = $this->ensureTestProvinceId();

        $this->employer = ProgramEmployer::create([
            'province_id'             => $provinceId,
            'name'                    => 'کارفرمای مدارک',
            'employer_code'           => '515101',
            'national_or_economic_id' => '5566778899',
            'mobile'                  => '09125556677',
        ]);

        $this->beneficiary = ProgramBeneficiary::create([
            'province_id'             => $provinceId,
            'name'                    => 'ذینفع مدارک',
            'beneficiary_code'        => '515201',
            'national_or_economic_id' => '1122334455',
            'mobile'                  => '09126667788',
        ]);
    }

    public function test_beneficiary_cost_document_paths_accessor(): void
    {
        $program = $this->createProgramWithBeneficiaryDocuments([
            'program-documents/beneficiary/9/contract.pdf',
            'storage/program-documents/beneficiary/9/scan.jpg',
        ]);

        $cost = $program->beneficiaryCosts->first();
        $this->assertNotNull($cost);

        $paths = $cost->documentPaths();
        $this->assertCount(2, $paths);
        $this->assertContains('program-documents/beneficiary/9/contract.pdf', $paths);
        $this->assertContains('program-documents/beneficiary/9/scan.jpg', $paths);
    }

    public function test_entity_detail_body_renders_download_link_for_beneficiary_document(): void
    {
        $storedPath = 'program-documents/beneficiary/42/debt-proof.pdf';

        $html = view('components.program.show-details._entity-detail-body', [
            'entity' => $this->beneficiary->fresh('province'),
            'type' => 'beneficiary',
            'panel' => 'admin',
            'debtAmount' => 750_000,
            'debtDescription' => 'بدهی با مدرک',
            'documents' => [$storedPath],
        ])->render();

        $this->assertStringContainsString('مدارک ضمیمه', $html);
        $this->assertStringContainsString('bi-file-pdf', $html);
        $this->assertStringContainsString('storage/' . $storedPath, $html);
        $this->assertStringContainsString('750,000', $html);
        $this->assertStringContainsString('اطلاعات تماس و هویت', $html);
    }

    public function test_program_show_renders_beneficiary_modal_with_document_download_link(): void
    {
        Storage::fake('public');

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();
        $beneficiaryFile = UploadedFile::fake()->create('beneficiary-proof.pdf', 120, 'application/pdf');

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            $this->basePayload($checkIn, $checkOut, [
                'beneficiary_costs' => [[
                    'program_beneficiary_id' => $this->beneficiary->id,
                    'debt_amount'            => 900_000,
                    'description'            => 'بدهی با پیوست',
                    'documents'              => [$beneficiaryFile],
                ]],
            ]),
            $this->hostUser,
        );

        $cost = $program->beneficiaryCosts->first();
        $this->assertNotEmpty($cost->documents);
        Storage::disk('public')->assertExists($cost->documents[0]);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program->fresh(['beneficiaryCosts.beneficiary.province', 'employer.province'])])
            ->assertSee('pg-modal-beneficiaries-' . $program->id)
            ->assertSee('ذینفع مدارک')
            ->assertSee('1 مدرک')
            ->assertSee('مدارک ضمیمه')
            ->assertSee('storage/' . $cost->documents[0])
            ->assertSee('bi-file-pdf')
            ->assertSee('900,000');
    }

    public function test_program_booking_form_stores_beneficiary_documents_via_dedicated_upload_property(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('beneficiary-upload.pdf', 100, 'application/pdf');

        Livewire::actingAs($this->hostUser)
            ->test(ProgramBookingForm::class, [
                'panel' => 'host',
                'accommodationId' => $this->accommodation->id,
            ])
            ->set('programEmployerId', (string) $this->employer->id)
            ->set('title', 'اردوی آپلود ذینفع')
            ->set('startDate', '1404/08/10')
            ->set('endDate', '1404/08/13')
            ->set('guestCount', 2)
            ->set('roomsAllocated', 1)
            ->set('basePrice', 1_000_000)
            ->set('paymentType', Program::PAYMENT_CASH)
            ->set('roomLines', [[
                'room_type_id'   => $this->roomType->id,
                'room_rate_id'   => $this->roomRate->id,
                'room_id'        => $this->room->id,
                'room_name'      => $this->room->name,
                'room_type_name' => $this->roomType->name,
            ]])
            ->set('beneficiaryRows', [[
                'program_beneficiary_id' => (string) $this->beneficiary->id,
                'debt_amount'            => 600_000,
                'description'            => 'بدهی آپلودی',
                'documents'              => [],
            ]])
            ->set('beneficiaryDocumentUploads.0', [$file])
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('step', 7);

        $program = Program::query()->where('title', 'اردوی آپلود ذینفع')->first();
        $this->assertNotNull($program);

        $cost = $program->beneficiaryCosts()->first();
        $this->assertNotNull($cost);
        $this->assertCount(1, $cost->documentPaths());
        Storage::disk('public')->assertExists($cost->documents[0]);
        $this->assertStringEndsWith('.pdf', $cost->documents[0]);
    }

    public function test_admin_and_host_routes_render_program_show_with_employer_modal(): void
    {
        $program = $this->createProgramWithBeneficiaryDocuments([
            'program-documents/beneficiary/1/file.pdf',
        ]);

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09124445566']);
        $admin->assignRole('super_admin');

        $this->actingAs($admin)
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('pg-modal-employer-' . $program->id)
            ->assertSee('کارفرمای مدارک')
            ->assertSee('اطلاعات تماس و هویت')
            ->assertDontSee('>نرخ<', false);

        $this->actingAs($this->hostUser)
            ->get(route('host.programs.show', $program))
            ->assertOk()
            ->assertSee('pg-modal-beneficiaries-' . $program->id)
            ->assertSee('ذینفع مدارک');
    }

    public function test_multiple_beneficiaries_render_tab_navigation_in_modal(): void
    {
        $second = ProgramBeneficiary::create([
            'province_id'             => $this->ensureTestProvinceId(),
            'name'                    => 'ذینفع دوم',
            'beneficiary_code'        => '515202',
            'national_or_economic_id' => '9988776655',
            'mobile'                  => '09120001122',
        ]);

        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            $this->basePayload($checkIn, $checkOut, [
                'beneficiary_costs' => [
                    [
                        'program_beneficiary_id' => $this->beneficiary->id,
                        'debt_amount'            => 300_000,
                        'description'            => 'اول',
                        'documents'              => [],
                    ],
                    [
                        'program_beneficiary_id' => $second->id,
                        'debt_amount'            => 400_000,
                        'description'            => 'دوم',
                        'documents'              => [],
                    ],
                ],
            ]),
            $this->hostUser,
        );

        $admin = User::create(['name' => 'ادمین', 'mobile' => '09123334444']);
        $admin->assignRole('super_admin');

        Livewire::actingAs($admin)
            ->test(ProgramShow::class, ['program' => $program->fresh(['beneficiaryCosts.beneficiary.province', 'employer.province'])])
            ->assertSee('pg-beneficiaries-tabs-' . $program->id)
            ->assertSee('ذینفع مدارک')
            ->assertSee('ذینفع دوم')
            ->assertSee('2 ذینفع');
    }

    public function test_program_document_paths_handles_null_and_empty(): void
    {
        $this->assertSame([], ProgramDocumentPaths::normalize(null));
        $this->assertSame([], ProgramDocumentPaths::normalize([]));
        $this->assertSame(0, ProgramDocumentPaths::count(null));
    }

    /** @param  list<string>  $documentPaths */
    private function createProgramWithBeneficiaryDocuments(array $documentPaths): Program
    {
        $checkIn = Carbon::today()->addDays(5)->toDateString();
        $checkOut = Carbon::today()->addDays(8)->toDateString();

        $program = app(ProgramBookingService::class)->create(
            $this->accommodation->fresh(),
            $this->basePayload($checkIn, $checkOut, [
                'beneficiary_costs' => [[
                    'program_beneficiary_id' => $this->beneficiary->id,
                    'debt_amount'            => 500_000,
                    'description'            => 'بدهی تست',
                    'documents'              => [],
                ]],
            ]),
            $this->hostUser,
        );

        ProgramBeneficiaryCost::query()
            ->where('program_id', $program->id)
            ->update(['documents' => $documentPaths]);

        return $program->fresh(['beneficiaryCosts.beneficiary.province', 'employer.province', 'booking.bookingRooms.room', 'booking.bookingRooms.roomType']);
    }

    /** @param  array<string, mixed>  $overrides */
    private function basePayload(string $checkIn, string $checkOut, array $overrides = []): array
    {
        return array_merge([
            'title'               => 'اردوی مدارک',
            'program_type'        => Program::TYPE_CAMP,
            'program_employer_id' => $this->employer->id,
            'guest_count'         => 2,
            'rooms_allocated'     => 1,
            'check_in'            => $checkIn,
            'check_out'           => $checkOut,
            'room_lines'          => [[
                'room_type_id' => $this->roomType->id,
                'room_rate_id' => $this->roomRate->id,
                'room_id'      => $this->room->id,
                'room_name'    => $this->room->name,
            ]],
            'payment_type'        => Program::PAYMENT_CASH,
            'base_price'          => 1_000_000,
            'beneficiary_costs'   => [],
        ], $overrides);
    }
}
