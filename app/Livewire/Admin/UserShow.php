<?php

namespace App\Livewire\Admin;

use App\Models\BookingBeneficiaryCost;
use App\Models\Program;
use App\Models\ProgramBeneficiaryCost;
use App\Models\User;
use App\Services\HostPersonnelCodeProvisioner;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin', ['title' => 'مشاهده کاربر', 'pageTitle' => 'جزئیات کاربر'])]
class UserShow extends Component
{
    public User $user;
    public string $selectedRole = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->user->load([
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

        $this->selectedRole = $this->user->roles->first()?->name ?? '';
    }

    public function assignRole(): void
    {
        if (!$this->selectedRole) return;

        $role = Role::findByName($this->selectedRole, 'web');
        $this->user->syncRoles([$role]);
        $this->user->refresh()->load('roles');
        session()->flash('status', 'نقش کاربر به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'نقش کاربر به‌روز شد.');
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
        $medicalEmployerBookings = collect();
        if ($user->programEmployer) {
            $programEmployerHistory = Program::query()
                ->with(['booking.accommodation', 'employer'])
                ->where('program_employer_id', $user->programEmployer->id)
                ->latest('id')
                ->take(20)
                ->get();

            $medicalEmployerBookings = \App\Models\Booking::query()
                ->with('accommodation')
                ->where('program_employer_id', $user->programEmployer->id)
                ->where('is_medical_accommodation', true)
                ->latest('id')
                ->take(20)
                ->get();
        }

        return view('admin.users.show', compact('user', 'programBeneficiaryHistory', 'bookingBeneficiaryHistory', 'programEmployerHistory', 'medicalEmployerBookings'));
    }
}
