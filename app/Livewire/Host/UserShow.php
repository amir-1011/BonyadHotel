<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\EnsuresHostManagedUserAccess;
use App\Models\BookingBeneficiaryCost;
use App\Models\Program;
use App\Models\ProgramBeneficiaryCost;
use App\Models\User;
use App\Services\HostPersonnelCodeProvisioner;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'مشاهده کاربر', 'pageTitle' => 'جزئیات کاربر'])]
class UserShow extends Component
{
    use EnsuresHostManagedUserAccess;

    public User $user;

    public function mount(User $user): void
    {
        $this->authorizeHostCan('users.show', 'read');
        $this->authorizeHostManagedUser($user);

        $this->user = $user->load([
            'roles',
            'bookings.accommodation',
            'accommodations.city.province',
            'accommodations.county.province',
            'province',
            'programBeneficiary.province',
            'programEmployer.province',
            'beneficiaryBookingCosts.booking.accommodation',
            'country',
            'residenceCity',
        ]);

        if ($this->user->isHost() && blank($this->user->personnel_code)) {
            $this->user = app(HostPersonnelCodeProvisioner::class)
                ->provisionIfNeeded($this->user)
                ->load([
                    'roles',
                    'bookings.accommodation',
                    'accommodations.city.province',
                    'accommodations.county.province',
                    'province',
                    'programBeneficiary.province',
                    'programEmployer.province',
                    'beneficiaryBookingCosts.booking.accommodation',
                    'country',
                    'residenceCity',
                ]);
        }
    }

    public function render()
    {
        $user = $this->user;

        $programBeneficiaryHistory = collect();
        if ($user->programBeneficiary) {
            $programBeneficiaryHistory = ProgramBeneficiaryCost::query()
                ->with(['program.booking.accommodation', 'beneficiary'])
                ->where('program_beneficiary_id', $user->programBeneficiary->id)
                ->latest('id')
                ->take(20)
                ->get();
        }

        $bookingBeneficiaryHistory = BookingBeneficiaryCost::query()
            ->with(['booking.accommodation', 'beneficiary'])
            ->where('user_id', $user->id)
            ->latest('id')
            ->take(20)
            ->get();

        $programEmployerHistory = collect();
        if ($user->programEmployer) {
            $programEmployerHistory = Program::query()
                ->with(['booking.accommodation', 'employer'])
                ->where('program_employer_id', $user->programEmployer->id)
                ->latest('id')
                ->take(20)
                ->get();
        }

        return view('host.users.show', compact(
            'user',
            'programBeneficiaryHistory',
            'bookingBeneficiaryHistory',
            'programEmployerHistory',
        ));
    }
}
