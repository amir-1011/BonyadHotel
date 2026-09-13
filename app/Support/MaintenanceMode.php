<?php

namespace App\Support;

class MaintenanceMode
{
    public static function flagPath(): string
    {
        return storage_path('framework/site_maintenance.json');
    }

    public static function isEnabled(): bool
    {
        $data = self::readFileData();

        if (is_array($data) && array_key_exists('enabled', $data)) {
            return (bool) $data['enabled'];
        }

        return filter_var(config('maintenance_mode.env_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function displayTitle(): string
    {
        $data = self::readFileData();

        if (is_array($data) && ! empty($data['title'])) {
            return (string) $data['title'];
        }

        return 'سامانه در حال بروزرسانی است';
    }

    public static function displayMessage(): string
    {
        $data = self::readFileData();

        if (is_array($data) && ! empty($data['message'])) {
            return (string) $data['message'];
        }

        return 'در حال اعمال تغییرات و بهبود سامانه رزرو هستیم.';
    }

    public static function setEnabled(bool $enabled, ?string $title = null, ?string $message = null): void
    {
        $path = self::flagPath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $payload = ['enabled' => $enabled];

        if ($enabled) {
            if ($title !== null) {
                $payload['title'] = $title;
            }
            if ($message !== null) {
                $payload['message'] = $message;
            }
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            LOCK_EX
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function readFileData(): ?array
    {
        $path = self::flagPath();

        if (! is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    public static function readEnvDefault(): bool
    {
        $envPath = base_path('.env');

        if (! is_readable($envPath)) {
            return false;
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^UNDER_MAINTENANCE\s*=\s*(.*)$/i', $line, $matches)) {
                return filter_var(trim($matches[1], " \t\"'"), FILTER_VALIDATE_BOOLEAN);
            }
        }

        return false;
    }
}
