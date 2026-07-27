<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesProgramShowGuests;
use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'جزئیات برنامه', 'pageTitle' => 'جزئیات برنامه'])]
class ProgramShow extends Component
{
    use AssertsHostPermissions;
    use ManagesProgramShowGuests;

    public Program $program;

    public function mount(Program $program): void
    {
        abort_unless(
            Auth::user()->managesAccommodation($program->accommodation_id),
            403
        );

        $this->program = $program->load($this->programShowRelations());
        $this->bootProgramShowGuests();
    }

    public function destroy(): void
    {
        $this->assertHostCan('programs.show', 'delete');
        if ($this->program->booking) {
            $this->program->booking->update(['status' => 'cancelled']);
        }

        $this->program->update(['status' => Program::STATUS_CANCELLED]);
        session()->flash('status', 'برنامه لغو شد.');
        $this->redirectRoute('host.programs.index', navigate: true);
    }

    /** @return list<string> */
    private function programShowRelations(): array
    {
        return [
            'accommodation.city.province',
            'createdBy',
            'booking.bookingRooms.room',
            'booking.bookingRooms.roomType',
            'booking.bookingRooms.roomRate',
            'booking.services',
            'booking.guestDetails.bookingRoom.room',
            'booking.guestDetails.country',
            'booking.guestDetails.residenceCity',
            'beneficiaryCosts.beneficiary.province',
            'beneficiaryCosts.beneficiary.user',
            'employer.province',
            'employer.user',
        ];
    }

    public function render()
    {
        return view('host.programs.show', ['program' => $this->program]);
    }
}
