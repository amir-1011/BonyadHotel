<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class BaleWebAppService
{
    public function validateInitData(string $initData): array
    {
        parse_str($initData, $data);

        $receivedHash = $data['hash'] ?? null;
        unset($data['hash']);

        if (!$receivedHash) {
            throw ValidationException::withMessages([
                'init_data' => 'اطلاعات ورود بله معتبر نیست.',
            ]);
        }

        $botToken = config('services.bale.bot_token');

        if (!$botToken) {
            throw ValidationException::withMessages([
                'init_data' => 'توکن بله در تنظیمات پروژه تعریف نشده است.',
            ]);
        }

        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($calculatedHash, $receivedHash)) {
            throw ValidationException::withMessages([
                'init_data' => 'امضای داده‌های مینی‌اپ معتبر نیست.',
            ]);
        }

        $authDate = (int) ($data['auth_date'] ?? 0);
        if ($authDate > 0 && $authDate < now()->subDay()->timestamp) {
            throw ValidationException::withMessages([
                'init_data' => 'داده‌های مینی‌اپ منقضی شده‌اند.',
            ]);
        }

        $user = [];

        if (!empty($data['user'])) {
            $decodedUser = json_decode($data['user'], true);
            if (is_array($decodedUser)) {
                $user = $decodedUser;
            }
        }

        return [
            'query_id' => $data['query_id'] ?? null,
            'auth_date' => $authDate ?: null,
            'user' => $user,
        ];
    }

    public function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (preg_match('/^09\d{9}$/', $digits)) {
            return $digits;
        }

        if (preg_match('/^98\d{10}$/', $digits)) {
            return '0'.substr($digits, 2);
        }

        return null;
    }
}