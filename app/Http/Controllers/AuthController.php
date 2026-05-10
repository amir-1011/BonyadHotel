<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\NationalIdVerificationService;
use App\Services\SmsService;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showMobileForm()
    {
        return view('auth.mobile');
    }

    public function sendOtp(Request $request, SmsService $sms)
    {
        $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل معتبر نیست. مثال: 09123456789',
        ]);

        $mobile = $request->input('mobile');

        // For testing / shortcut: use a fixed OTP for all users
        $otp = '123456';
        $sms->sendOtp($mobile, $otp);

        session(['otp_mobile' => $mobile]);

        return redirect()->route('auth.otp.form')
            ->with('status', "کد تأیید به شماره {$mobile} ارسال شد.");
    }

    public function showOtpForm()
    {
        if (!session('otp_mobile')) {
            return redirect()->route('auth.mobile');
        }

        return view('auth.otp', ['mobile' => session('otp_mobile')]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'کد تأیید الزامی است.',
            'otp.digits'   => 'کد تأیید باید ۶ رقم باشد.',
        ]);

        $mobile    = session('otp_mobile');
        // Accept fixed OTP 123456 for all users (testing shortcut)
        if ($request->input('otp') !== '123456') {
            $otpResult = (new Otp)->validate($mobile, $request->input('otp'));

            if (!$otpResult->status) {
                return back()->withErrors(['otp' => 'کد وارد شده نامعتبر یا منقضی شده است.']);
            }
        }

        // Default national ID with discount
        $defaultNationalId = '4440000008';
        $verificationService = app(NationalIdVerificationService::class);
        $idVerification = $verificationService->verify($defaultNationalId);

        $user = User::firstOrCreate(
            ['mobile' => $mobile],
            [
                'mobile_verified_at' => now(),
                'national_id' => $defaultNationalId,
                'veteran_type' => $idVerification['veteran_type'],
                'discount_percentage' => $idVerification['discount'],
                'national_id_verified_at' => now(),
            ]
        );

        if ($user->wasRecentlyCreated || !$user->mobile_verified_at) {
            $user->update(['mobile_verified_at' => now()]);
        }

        Auth::login($user, true);
        session()->forget('otp_mobile');

        // New users without a name go to profile setup
        if (!$user->name) {
            return redirect()->route('profile.setup')
                ->with('status', 'خوش آمدید! لطفاً اطلاعات خود را تکمیل کنید.');
        }

        return redirect()->intended(route('home'))
            ->with('status', 'با موفقیت وارد شدید.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
