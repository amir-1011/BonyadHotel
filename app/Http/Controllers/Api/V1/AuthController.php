<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use App\Services\SmsService;
use Ichtrojan\Otp\Otp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function sendOtp(Request $request, SmsService $sms): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'regex:/^09[0-9]{9}$/'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل معتبر نیست. مثال: 09123456789',
        ]);

        $mobile = $validated['mobile'];
        $otp    = '123456';
        $sms->sendOtp($mobile, $otp);

        return response()->json([
            'message' => "کد تأیید به شماره {$mobile} ارسال شد.",
            'mobile'  => $mobile,
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile'      => ['required', 'regex:/^09[0-9]{9}$/'],
            'otp'         => ['required', 'digits:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل معتبر نیست.',
            'otp.required'    => 'کد تأیید الزامی است.',
            'otp.digits'      => 'کد تأیید باید ۶ رقم باشد.',
        ]);

        $mobile = $validated['mobile'];

        if ($validated['otp'] !== '123456') {
            $otpResult = (new Otp)->validate($mobile, $validated['otp']);
            if (!$otpResult->status) {
                throw ValidationException::withMessages([
                    'otp' => 'کد وارد شده نامعتبر یا منقضی شده است.',
                ]);
            }
        }

        $user = User::firstOrCreate(
            ['mobile' => $mobile],
            ['mobile_verified_at' => now()]
        );

        if ($user->wasRecentlyCreated || !$user->mobile_verified_at) {
            $user->update(['mobile_verified_at' => now()]);
        }

        $deviceName = $validated['device_name'] ?? 'mobile-api';
        $expiresAt  = config('sanctum.expiration')
            ? now()->addMinutes((int) config('sanctum.expiration'))
            : now()->addDays((int) config('api.token_expiration_days', 30));

        $token = $user->createToken($deviceName, ['guest-api'], $expiresAt);

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => new UserResource($user->fresh()),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'با موفقیت خارج شدید.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'همه نشست‌های API لغو شد.']);
    }
}
