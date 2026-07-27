<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\BookingService;
use App\Models\Program;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramBeneficiaryCost;
use App\Models\ProgramEmployer;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProgramBookingService
{
    public function __construct(
        private readonly RoomAvailabilityService $roomAvailability,
        private readonly ProgramDocumentService $documents,
        private readonly PlatformCommissionService $commission,
        private readonly BeneficiaryUserProvisioner $beneficiaryUsers,
        private readonly EmployerUserProvisioner $employerUsers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Accommodation $accommodation, array $data, User $createdBy): Program
    {
        return DB::transaction(function () use ($accommodation, $data, $createdBy) {
            $checkIn = (string) $data['check_in'];
            $checkOut = (string) $data['check_out'];
            $roomLines = $this->normalizeRoomLines($accommodation, $data['room_lines'] ?? []);
            $services = $data['services'] ?? [];
            $beneficiaryCosts = $data['beneficiary_costs'] ?? [];

            $this->assertRoomAvailability($accommodation, $checkIn, $checkOut, $roomLines);

            $servicesSubtotal = $this->sumServices($services);
            $basePrice = (int) ($data['base_price'] ?? 0);
            $discountAmount = (int) ($data['discount_amount'] ?? 0);
            $depositAmount = (int) ($data['deposit_amount'] ?? 0);
            $totalAmount = max(0, $basePrice + $servicesSubtotal - $discountAmount);

            $nights = max(1, Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)));

            $paymentDocs = $this->documents->storeMany(
                $data['payment_documents'] ?? [],
                'program-documents/payment/' . $accommodation->id,
            );

            $guestListDocs = $this->documents->storeMany(
                $data['guest_list_documents'] ?? [],
                'program-documents/guest-list/' . $accommodation->id,
            );

            $employerId = (int) ($data['program_employer_id'] ?? 0);
            $employer = $employerId > 0 ? ProgramEmployer::find($employerId) : null;

            if ($employer && !$employer->user_id) {
                $employer = $this->employerUsers->linkEmployer($employer);
            }

            $guestContactName = $employer?->name ?? (string) ($data['title'] ?? 'برنامه');

            $booking = Booking::create([
                'user_id'              => $createdBy->id,
                'created_by'           => $createdBy->id,
                'accommodation_id'     => $accommodation->id,
                'room_type_id'         => $roomLines[0]['room_type_id'] ?? null,
                'room_rate_id'         => $roomLines[0]['room_rate_id'] ?? null,
                'check_in'             => $checkIn,
                'check_out'            => $checkOut,
                'guests'               => (int) ($data['guest_count'] ?? 1),
                'children_under_6'     => 0,
                'guest_contact_name'   => $guestContactName,
                'guest_contact_mobile' => '',
                'rooms_consumed'       => count($roomLines),
                'extra_guests'         => 0,
                'extra_guests_price'   => 0,
                'bill_full_rooms'      => true,
                'nights'               => $nights,
                'base_price'           => $basePrice + $servicesSubtotal,
                'services_subtotal'    => $servicesSubtotal,
                'discount_percentage'  => 0,
                'discount_amount'      => $discountAmount,
                'total_price'          => $totalAmount,
                'status'               => 'confirmed',
                'booking_source'       => 'program',
                'payment_method'       => $this->mapPaymentMethod($data['payment_type'] ?? Program::PAYMENT_CASH),
                'notes'                => $data['notes'] ?? null,
                'tracking_code'        => strtoupper(Str::random(10)),
            ]);

            foreach ($roomLines as $index => $line) {
                BookingRoom::create([
                    'booking_id'       => $booking->id,
                    'room_type_id'     => $line['room_type_id'],
                    'room_rate_id'     => $line['room_rate_id'],
                    'room_id'          => $line['room_id'],
                    'adults'           => 1,
                    'children_under_6' => 0,
                    'guests'           => 1,
                    'extra_guests'     => 0,
                    'bill_full_rooms'  => true,
                    'rooms_consumed'   => 1,
                    'sort_order'       => $index,
                ]);
            }

            $bookingRoomIdsBySort = $booking->bookingRooms()
                ->orderBy('sort_order')
                ->pluck('id', 'sort_order')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->persistProgramGuests(
                $booking,
                $data['guest_details'] ?? [],
                $bookingRoomIdsBySort,
                (string) $guestContactName,
            );

            foreach ($services as $index => $service) {
                $qty = max(1, (int) ($service['quantity'] ?? 1));
                $unitPrice = (int) ($service['unit_price'] ?? 0);
                $lineTotal = $qty * $unitPrice;

                BookingService::create([
                    'booking_id'                 => $booking->id,
                    'service_catalog_id'         => $service['service_catalog_id'] ?? null,
                    'service_catalog_variant_id' => $service['service_catalog_variant_id'] ?? null,
                    'name'                       => (string) ($service['name'] ?? ''),
                    'unit_price'                 => $unitPrice,
                    'quantity'                   => $qty,
                    'discount_percentage'        => 0,
                    'discount_amount'            => 0,
                    'total'                      => $lineTotal,
                    'sort_order'                 => $index,
                ]);
            }

            $program = Program::create([
                'booking_id'        => $booking->id,
                'accommodation_id'  => $accommodation->id,
                'created_by'        => $createdBy->id,
                'title'             => (string) $data['title'],
                'description'       => $data['description'] ?? null,
                'program_type'      => (string) ($data['program_type'] ?? Program::TYPE_CAMP),
                'program_employer_id' => $employer?->id,
                'contractor'        => $data['contractor'] ?? null,
                'guest_count'       => (int) ($data['guest_count'] ?? 1),
                'rooms_allocated'   => (int) ($data['rooms_allocated'] ?? count($roomLines)),
                'payment_type'      => (string) ($data['payment_type'] ?? Program::PAYMENT_CASH),
                'payment_documents' => $paymentDocs,
                'guest_list_documents' => $guestListDocs,
                'base_price'        => $basePrice,
                'services_subtotal' => $servicesSubtotal,
                'discount_amount'   => $discountAmount,
                'deposit_amount'    => $depositAmount,
                'total_amount'      => $totalAmount,
                'status'            => Program::STATUS_ACTIVE,
                'notes'             => $data['notes'] ?? null,
            ]);

            foreach ($beneficiaryCosts as $row) {
                $beneficiaryId = (int) ($row['program_beneficiary_id'] ?? 0);
                if ($beneficiaryId <= 0) {
                    continue;
                }

                $beneficiary = ProgramBeneficiary::find($beneficiaryId);
                if ($beneficiary && !$beneficiary->user_id) {
                    $beneficiary = $this->beneficiaryUsers->linkBeneficiary($beneficiary);
                }

                $docs = $this->documents->storeMany(
                    $row['documents'] ?? [],
                    'program-documents/beneficiary/' . $program->id,
                );

                ProgramBeneficiaryCost::create([
                    'program_id'              => $program->id,
                    'program_beneficiary_id'    => $beneficiaryId,
                    'debt_amount'             => (int) ($row['debt_amount'] ?? 0),
                    'description'             => $row['description'] ?? null,
                    'documents'               => $docs,
                ]);
            }

            $this->commission->syncBookingCommissions($booking, $createdBy);

            return $program->load(['booking.bookingRooms.room', 'booking.services', 'booking.guestDetails.bookingRoom.room', 'beneficiaryCosts.beneficiary', 'employer', 'accommodation']);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array{room_type_id:int, room_rate_id:?int, room_id:int, room_name:string}>
     */
    private function normalizeRoomLines(Accommodation $accommodation, array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $roomId = (int) ($line['room_id'] ?? 0);
            $roomTypeId = (int) ($line['room_type_id'] ?? 0);

            if ($roomId <= 0 || $roomTypeId <= 0) {
                continue;
            }

            $room = Room::with('roomType')->find($roomId);
            if (!$room || $room->roomType?->accommodation_id !== $accommodation->id) {
                throw new \RuntimeException('اتاق انتخاب‌شده معتبر نیست.');
            }

            if ((int) $room->room_type_id !== $roomTypeId) {
                throw new \RuntimeException('نوع اتاق با اتاق فیزیکی هم‌خوانی ندارد.');
            }

            $rateId = (int) ($line['room_rate_id'] ?? 0);
            $rate = $rateId > 0
                ? RoomRate::where('room_type_id', $roomTypeId)->find($rateId)
                : RoomRate::where('room_type_id', $roomTypeId)->where('is_active', true)->orderBy('id')->first();

            $normalized[] = [
                'room_type_id' => $roomTypeId,
                'room_rate_id' => $rate?->id,
                'room_id'      => $roomId,
                'room_name'    => (string) ($line['room_name'] ?? $room->name),
            ];
        }

        if ($normalized === []) {
            throw new \RuntimeException('حداقل یک اتاق فیزیکی باید انتخاب شود.');
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{room_type_id:int, room_rate_id:?int, room_id:int}>  $roomLines
     */
    private function assertRoomAvailability(Accommodation $accommodation, string $checkIn, string $checkOut, array $roomLines): void
    {
        // Programs assign specific physical rooms — skip aggregate room-type capacity checks.
        $assignedIds = [];

        foreach ($roomLines as $line) {
            $room = Room::find($line['room_id']);
            if (!$room) {
                throw new \RuntimeException('اتاق اختصاص‌داده‌شده یافت نشد.');
            }

            if ($room->roomType?->accommodation_id !== $accommodation->id) {
                throw new \RuntimeException('اتاق «' . $room->name . '» به این اقامتگاه تعلق ندارد.');
            }

            $others = array_values(array_diff($assignedIds, [(int) $line['room_id']]));
            if (!$this->roomAvailability->isRoomAvailable($room, $checkIn, $checkOut, $others)) {
                throw new \RuntimeException('اتاق «' . $room->name . '» در بازه انتخابی در دسترس نیست.');
            }

            $assignedIds[] = (int) $line['room_id'];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     */
    private function sumServices(array $services): int
    {
        $total = 0;

        foreach ($services as $service) {
            if (empty(trim((string) ($service['name'] ?? '')))) {
                continue;
            }

            $qty = max(1, (int) ($service['quantity'] ?? 1));
            $unitPrice = (int) ($service['unit_price'] ?? 0);
            $total += $qty * $unitPrice;
        }

        return $total;
    }

    private function mapPaymentMethod(string $paymentType): ?string
    {
        return match ($paymentType) {
            Program::PAYMENT_CREDIT, Program::PAYMENT_SUPPORTIVE => 'cash',
            default => 'cash',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $guestDetails
     * @param  array<int, int>  $bookingRoomIdsBySort
     */
    private function persistProgramGuests(
        Booking $booking,
        array $guestDetails,
        array $bookingRoomIdsBySort,
        string $guestContactName,
    ): void {
        if ($guestDetails === []) {
            return;
        }

        foreach ($guestDetails as $index => $guest) {
            $fullName = trim((string) ($guest['full_name'] ?? ''));
            if ($fullName === '') {
                continue;
            }

            $sortOrder = (int) ($guest['sort_order'] ?? $index);
            $roomLineIndex = isset($guest['room_line_index']) ? (int) $guest['room_line_index'] : null;
            $bookingRoomId = ($roomLineIndex !== null && isset($bookingRoomIdsBySort[$roomLineIndex]))
                ? $bookingRoomIdsBySort[$roomLineIndex]
                : null;

            $relation = trim((string) ($guest['relation'] ?? ''));
            if ($sortOrder === 0 && $relation === '') {
                $relation = BookingGuestDetail::RELATION_MAIN_GUEST;
            }

            BookingGuestDetail::create([
                'booking_id'      => $booking->id,
                'booking_room_id' => $bookingRoomId,
                'sort_order'      => $sortOrder,
                'full_name'       => $fullName,
                'national_id'     => $guest['national_id'] ?? null,
                'mobile'          => $guest['mobile'] ?? null,
                'relation'        => $relation ?: null,
            ]);
        }
    }
}
