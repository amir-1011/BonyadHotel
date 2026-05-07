<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Mock SMS Service
 * 
 * For production, replace with Kavenegar, SMS.ir, Farazsms, etc.
 * OTPs are logged to storage/logs/laravel.log for testing.
 */
class SmsService
{
    public function sendOtp(string $mobile, string $otp): bool
    {
        // Mock: just log the OTP
        Log::info("OTP for {$mobile}: {$otp}");

        // Uncomment below for real Kavenegar integration:
        // \Kavenegar\KavenegareApi::Send(env('KAVENEGAR_API_KEY'), $mobile, "کد تأیید: {$otp}");

        return true;
    }
}
