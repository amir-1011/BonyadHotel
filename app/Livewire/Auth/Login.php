<?php

namespace App\Livewire\Auth;

use App\Services\SmsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'ورود با شماره موبایل'])]
class Login extends Component
{
    public string $mobile = '';

    public function sendOtp(SmsService $sms): void
    {
        $this->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل معتبر نیست. مثال: 09123456789',
        ]);

        // Fixed OTP for all users (testing shortcut)
        $otp = '123456';
        $sms->sendOtp($this->mobile, $otp);

        session(['otp_mobile' => $this->mobile]);

        session()->flash('status', "کد تأیید به شماره {$this->mobile} ارسال شد.");
        $this->redirectRoute('auth.otp.form', navigate: true);
    }

    public function render()
    {
        return view('auth.mobile');
    }
}
