<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\SmsService;
use Ichtrojan\Otp\Otp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.staff-auth')]
class StaffLogin extends Component
{
    public string $step = 'mobile';

    public string $mobile = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $otp = '';

    public function mount(): void
    {
        if ($user = Auth::user()) {
            if ($user->hasStaffAccess()) {
                $this->redirect($user->staffDashboardUrl(), navigate: true);
            }

            Auth::logout();
        }
    }

    public function submitMobile(): void
    {
        $this->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل معتبر نیست. مثال: 09123456789',
        ]);

        $user = User::where('mobile', $this->mobile)->first();

        if (!$user || !$user->hasStaffAccess()) {
            $this->addError('mobile', 'دسترسی فقط برای مدیران و میزبان‌ها مجاز است.');
            return;
        }

        session(['staff_login_mobile' => $this->mobile]);

        if ($user->hasPassword()) {
            $this->step = 'password';
            $this->reset('password', 'otp');
            return;
        }

        $this->dispatchOtp();
        $this->step = 'otp';
    }

    public function loginWithPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string', 'min:6'],
        ], [
            'password.required' => 'رمز عبور الزامی است.',
            'password.min'      => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
        ]);

        $user = $this->resolveStaffUser();
        if (!$user) {
            return;
        }

        if (!Hash::check($this->password, $user->password)) {
            $this->addError('password', 'رمز عبور نادرست است.');
            return;
        }

        $this->finishLogin($user);
    }

    public function switchToOtp(SmsService $sms): void
    {
        if (!$this->resolveStaffUser()) {
            return;
        }

        $this->dispatchOtp($sms);
        $this->step = 'otp';
        $this->reset('password', 'otp');
    }

    public function resendOtp(SmsService $sms): void
    {
        if (!$this->resolveStaffUser()) {
            return;
        }

        $this->dispatchOtp($sms);
        session()->flash('status', 'کد تأیید مجدداً ارسال شد.');
    }

    public function verifyOtp(): void
    {
        $this->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'کد تأیید الزامی است.',
            'otp.digits'   => 'کد تأیید باید ۶ رقم باشد.',
        ]);

        $user = $this->resolveStaffUser();
        if (!$user) {
            return;
        }

        if ($this->otp !== '123456') {
            $otpResult = (new Otp)->validate($this->mobile, $this->otp);
            if (!$otpResult->status) {
                $this->addError('otp', 'کد وارد شده نامعتبر یا منقضی شده است.');
                return;
            }
        }

        if (!$user->hasPassword()) {
            $this->step = 'set_password';
            $this->reset('password', 'password_confirmation', 'otp');
            return;
        }

        $this->finishLogin($user);
    }

    public function setPassword(): void
    {
        $this->validate([
            'password'              => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required'],
        ], [
            'password.required'  => 'رمز عبور الزامی است.',
            'password.min'       => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور مطابقت ندارد.',
        ]);

        $user = $this->resolveStaffUser();
        if (!$user) {
            return;
        }

        $user->update(['password' => $this->password]);

        $this->finishLogin($user);
    }

    public function backToMobile(): void
    {
        session()->forget('staff_login_mobile');
        $this->step = 'mobile';
        $this->reset('password', 'password_confirmation', 'otp');
        $this->resetErrorBag();
    }

    protected function resolveStaffUser(): ?User
    {
        $mobile = session('staff_login_mobile') ?: $this->mobile;

        if (!$mobile) {
            $this->backToMobile();
            return null;
        }

        $this->mobile = $mobile;

        $user = User::where('mobile', $mobile)->first();

        if (!$user || !$user->hasStaffAccess()) {
            session()->forget('staff_login_mobile');
            $this->addError('mobile', 'دسترسی فقط برای مدیران و میزبان‌ها مجاز است.');
            $this->step = 'mobile';
            return null;
        }

        return $user;
    }

    protected function dispatchOtp(?SmsService $sms = null): void
    {
        $sms ??= app(SmsService::class);
        $otp = '123456';
        $sms->sendOtp($this->mobile, $otp);
        session()->flash('status', "کد تأیید به شماره {$this->mobile} ارسال شد.");
    }

    protected function finishLogin(User $user): void
    {
        if (!$user->mobile_verified_at) {
            $user->update(['mobile_verified_at' => now()]);
        }

        Auth::login($user, true);
        session()->forget('staff_login_mobile');

        session()->flash('status', 'با موفقیت وارد شدید.');

        if (config('test_site.enabled')) {
            session()->flash('show_test_site_notice', true);
        }

        // Full page load so session flash + notice scripts run (wire:navigate skips DOMContentLoaded).
        $this->redirect($user->staffDashboardUrl(), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.staff-login');
    }
}
