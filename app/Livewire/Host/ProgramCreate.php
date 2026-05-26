<?php

namespace App\Livewire\Host;

use App\Models\Program;
use App\Models\RoomType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'ثبت برنامه جدید', 'pageTitle' => 'ثبت برنامه'])]
class ProgramCreate extends Component
{
    public int    $accommodationId       = 0;
    public string $title                 = '';
    public string $description           = '';
    public string $programType           = 'camp';
    public string $startDate             = '';
    public string $endDate               = '';
    public int    $roomsAllocated        = 1;
    public int    $guestCount            = 1;
    public string $employer              = '';
    public string $contractor            = '';
    public int    $totalAmount           = 0;
    public int    $depositAmount         = 0;
    public int    $discountAmount        = 0;
    public int    $discountPercentage    = 0;
    public bool   $isSupportiveService   = false;
    public string $supportiveServiceType = '';
    public string $notes                 = '';
    public array  $selectedRoomTypes     = []; // [id => count]

    public function mount(): void
    {
        $first = Auth::user()->accommodations()->first();
        if ($first) $this->accommodationId = $first->id;
    }

    public function updatedAccommodationId(): void
    {
        $this->selectedRoomTypes = [];
    }

    protected function rules(): array
    {
        return [
            'accommodationId'       => ['required', 'integer'],
            'title'                 => ['required', 'string', 'max:200'],
            'description'           => ['nullable', 'string', 'max:2000'],
            'programType'           => ['required', 'in:camp,event,other'],
            'startDate'             => ['required', 'date'],
            'endDate'               => ['required', 'date', 'after_or_equal:startDate'],
            'roomsAllocated'        => ['required', 'integer', 'min:1'],
            'guestCount'            => ['required', 'integer', 'min:1'],
            'employer'              => ['nullable', 'string', 'max:200'],
            'contractor'            => ['nullable', 'string', 'max:200'],
            'totalAmount'           => ['required', 'integer', 'min:0'],
            'depositAmount'         => ['nullable', 'integer', 'min:0'],
            'discountAmount'        => ['nullable', 'integer', 'min:0'],
            'discountPercentage'    => ['nullable', 'integer', 'min:0', 'max:100'],
            'isSupportiveService'   => ['nullable', 'boolean'],
            'supportiveServiceType' => ['nullable', 'string', 'max:200'],
            'notes'                 => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function store(): void
    {
        $this->validate();

        $accIds = Auth::user()->accommodations()->pluck('id');
        if (!$accIds->contains($this->accommodationId)) {
            $this->addError('accommodationId', 'اقامتگاه مجاز نیست.');
            return;
        }

        $program = Program::create([
            'accommodation_id'        => $this->accommodationId,
            'title'                   => $this->title,
            'description'             => $this->description ?: null,
            'program_type'            => $this->programType,
            'start_date'              => $this->startDate,
            'end_date'                => $this->endDate,
            'rooms_allocated'         => $this->roomsAllocated,
            'guest_count'             => $this->guestCount,
            'employer'                => $this->employer ?: null,
            'contractor'              => $this->contractor ?: null,
            'total_amount'            => $this->totalAmount,
            'deposit_amount'          => $this->depositAmount ?? 0,
            'discount_amount'         => $this->discountAmount ?? 0,
            'discount_percentage'     => $this->discountPercentage ?? 0,
            'is_supportive_service'   => $this->isSupportiveService,
            'supportive_service_type' => $this->supportiveServiceType ?: null,
            'notes'                   => $this->notes ?: null,
            'status'                  => 'active',
        ]);

        if (!empty($this->selectedRoomTypes)) {
            $sync = [];
            foreach ($this->selectedRoomTypes as $id => $count) {
                if ($count > 0) $sync[(int) $id] = ['rooms_count' => (int) $count];
            }
            $program->roomTypes()->sync($sync);
        }

        session()->flash('status', 'برنامه «' . $program->title . '» با موفقیت ثبت شد.');
        $this->redirectRoute('host.programs.show', $program, navigate: true);
    }

    public function render()
    {
        $myAccommodations = Auth::user()->accommodations()->orderBy('name')->get(['id', 'name']);
        $roomTypes        = collect();

        if ($this->accommodationId) {
            $accIds = Auth::user()->accommodations()->pluck('id');
            if ($accIds->contains($this->accommodationId)) {
                $roomTypes = RoomType::where('accommodation_id', $this->accommodationId)
                    ->where('is_active', true)->get(['id', 'name', 'room_count']);
            }
        }

        return view('host.programs.create', compact('myAccommodations', 'roomTypes'));
    }
}
