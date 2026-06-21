<?php

namespace App\Livewire\Host;

use App\Models\Program;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

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
            'startDate'             => ['required', 'string'],
            'endDate'               => ['required', 'string'],
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

        $startGreg = $this->toGregorian($this->startDate);
        $endGreg   = $this->toGregorian($this->endDate);

        if (!$startGreg) {
            $this->addError('startDate', 'تاریخ شروع معتبر نیست. فرمت: ۱۴۰۴/۰۱/۰۱');
            return;
        }
        if (!$endGreg) {
            $this->addError('endDate', 'تاریخ پایان معتبر نیست. فرمت: ۱۴۰۴/۰۱/۰۱');
            return;
        }
        if ($endGreg < $startGreg) {
            $this->addError('endDate', 'تاریخ پایان باید بعد از تاریخ شروع باشد.');
            return;
        }

        $accIds = Auth::user()->managedAccommodationIds();
        if (!$accIds->contains($this->accommodationId)) {
            $this->addError('accommodationId', 'اقامتگاه مجاز نیست.');
            return;
        }

        $program = Program::create([
            'accommodation_id'        => $this->accommodationId,
            'title'                   => $this->title,
            'description'             => $this->description ?: null,
            'program_type'            => $this->programType,
            'start_date'              => $startGreg,
            'end_date'                => $endGreg,
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
        $myAccommodations = Auth::user()->managedAccommodationOptions();
        $roomTypes        = collect();

        if ($this->accommodationId) {
            $accIds = Auth::user()->managedAccommodationIds();
            if ($accIds->contains($this->accommodationId)) {
                $roomTypes = RoomType::where('accommodation_id', $this->accommodationId)
                    ->where('is_active', true)->get(['id', 'name', 'room_count']);
            }
        }

        return view('host.programs.create', compact('myAccommodations', 'roomTypes'));
    }

    private function toGregorian(?string $jalali): ?string
    {
        if (!$jalali) {
            return null;
        }

        try {
            $normalized = strtr(trim($jalali), [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);

            return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
