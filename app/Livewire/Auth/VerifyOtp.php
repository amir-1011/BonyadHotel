<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'تأیید کد'])]
class VerifyOtp extends Component
{
    public string $otp    = '';
    public string $mobile = '';

    public function mount(): void
    {
        if (!session('otp_mobile')) {
            $this->redirectRoute('auth.mobile', navigate: true);
            return;
        }
        $this->mobile = session('otp_mobile');
    }

    public function verify(): void
    {
        $this->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'کد تأیید الزامی است.',
            'otp.digits'   => 'کد تأیید باید ۶ رقم باشد.',
        ]);

        $mobile = session('otp_mobile');

        // Accept fixed OTP 123456 for all users (testing shortcut)
        if ($this->otp !== '123456') {
            $otpResult = (new Otp)->validate($mobile, $this->otp);
            if (!$otpResult->status) {
                $this->addError('otp', 'کد وارد شده نامعتبر یا منقضی شده است.');
                return;
            }
        }

        $user = User::firstOrCreate(
            ['mobile' => $mobile],
            [
                'mobile_verified_at' => now(),
            ]
        );

        if ($user->wasRecentlyCreated || !$user->mobile_verified_at) {
            $user->update(['mobile_verified_at' => now()]);
        }

        Auth::login($user, true);
        session()->forget('otp_mobile');

        if (!$user->name) {
            session()->flash('status', 'خوش آمدید! لطفاً اطلاعات خود را تکمیل کنید.');
            $this->redirectRoute('profile.setup', navigate: true);
            return;
        }

        session()->flash('status', 'با موفقیت وارد شدید.');
        $this->redirectIntended(route('home'), navigate: true);
    }

    public function render()
    {
        return view('auth.otp', ['mobile' => $this->mobile]);
    }
}
