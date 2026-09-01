<?php

namespace App\Support;

use App\Services\VeteranPolicyProvisioner;

/**
 * Report groups for the supportive-services dashboard (programs + base veteran groups).
 */
final class SupportiveServicesReportGroups
{
    public const KEY_SUPPORTIVE_PROGRAM = 'supportive_program';

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function sharedReportGroups(): array
    {
        $groups = [
            [
                'key'   => self::KEY_SUPPORTIVE_PROGRAM,
                'label' => 'برنامه / اردوی حمایتی',
            ],
        ];

        foreach (self::veteranGroupDefinitions() as $def) {
            $groups[] = [
                'key'   => $def['key'],
                'label' => $def['label'],
            ];
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function veteranGroupKeys(): array
    {
        return array_column(self::veteranGroupDefinitions(), 'key');
    }

    /** @return array<string, string> */
    public static function veteranGroupLabels(): array
    {
        $labels = [];

        foreach (self::veteranGroupDefinitions() as $def) {
            $labels[$def['key']] = $def['label'];
        }

        return $labels;
    }

    public static function labelForKey(string $key): string
    {
        foreach (self::sharedReportGroups() as $group) {
            if ($group['key'] === $key) {
                return $group['label'];
            }
        }

        return $key;
    }

    /** @return array<int, array<string, mixed>> */
    private static function veteranGroupDefinitions(): array
    {
        return app(VeteranPolicyProvisioner::class)->groupDefinitions();
    }
}
