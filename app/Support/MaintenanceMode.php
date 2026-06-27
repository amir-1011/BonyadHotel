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
        $path = self::flagPath();

        if (is_readable($path)) {
            $data = json_decode((string) file_get_contents($path), true);

            if (is_array($data) && array_key_exists('enabled', $data)) {
                return (bool) $data['enabled'];
            }
        }

        return filter_var(config('maintenance_mode.env_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function setEnabled(bool $enabled): void
    {
        $path = self::flagPath();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(['enabled' => $enabled], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            LOCK_EX
        );
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
