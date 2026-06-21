<?php

namespace App\Livewire\Host;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'پروفایل', 'pageTitle' => 'پروفایل'])]
class Profile extends Component
{
    public string $currentPassword = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function changePassword(): void
    {
        $user = Auth::user();

        $this->validate([
            'currentPassword'       => ['required', 'string'],
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required'],
        ], [
            'currentPassword.required' => 'رمز عبور فعلی الزامی است.',
            'password.required'        => 'رمز عبور جدید الزامی است.',
            'password.min'             => 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.',
            'password.confirmed'       => 'تکرار رمز عبور جدید مطابقت ندارد.',
        ]);

        if (!$user->hasPassword() || !Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'رمز عبور فعلی نادرست است.');
            return;
        }

        if (Hash::check($this->password, $user->password)) {
            $this->addError('password', 'رمز عبور جدید نباید با رمز فعلی یکسان باشد.');
            return;
        }

        $user->update(['password' => $this->password]);

        $this->reset('currentPassword', 'password', 'password_confirmation');
        session()->flash('status', 'رمز عبور با موفقیت تغییر کرد.');
    }

    public function render()
    {
        return view('host.profile', [
            'user' => Auth::user(),
        ]);
    }
}
