<?php

namespace App\Livewire\Pages;

use App\Services\NationalIdVerificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'پروفایل من'])]
class ProfileIndex extends Component
{
    use WithPagination;

    public string $nationalId = '';

    public function verifyNationalId(): void
    {
        $this->validate([
            'nationalId' => ['required', 'digits:10'],
        ], [
            'nationalId.required' => 'کد ملی الزامی است.',
            'nationalId.digits'   => 'کد ملی باید ۱۰ رقم باشد.',
        ]);

        $result = app(NationalIdVerificationService::class)->verify($this->nationalId);

        if (!$result['valid']) {
            $this->addError('nationalId', $result['message']);
            return;
        }

        Auth::user()->update([
            'national_id'             => $this->nationalId,
            'veteran_type'            => $result['veteran_type'],
            'discount_percentage'     => $result['discount'],
            'national_id_verified_at' => now(),
        ]);

        $this->nationalId = '';
        session()->flash('status', 'کد ملی با موفقیت تأیید شد.');
        $this->dispatch('toast', type: 'success', message: 'کد ملی با موفقیت تأیید شد.');
    }

    public function render()
    {
        $user     = Auth::user();
        $bookings = $user->bookings()->with('accommodation.city.province')
            ->latest()->paginate(10);

        return view('profile.index', compact('user', 'bookings'));
    }
}
