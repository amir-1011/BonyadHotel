<?php

namespace App\Support;

class AdminStatSparkline
{
    /**
     * @return list<float>
     */
    public static function points(float $seed, int $count = 10): array
    {
        $points = [];
        $base = max(1.0, abs($seed));

        for ($i = 0; $i < $count; $i++) {
            $wobble = 0.55 + 0.45 * sin(($i * 1.7) + ($seed * 0.013));
            $points[] = round($base * $wobble * (0.82 + $i * 0.028), 2);
        }

        return $points;
    }

    /**
     * @param  list<float|int>  $values
     */
    public static function svg(array $values, string $type = 'line'): string
    {
        if ($values === []) {
            $values = [1, 2, 1.5, 2.5, 2, 3, 2.8, 3.5];
        }

        $width = 220;
        $height = 64;
        $padding = 6;
        $max = (float) max($values);
        $min = (float) min($values);
        $range = max($max - $min, 1.0);
        $count = count($values);

        $coords = [];
        foreach ($values as $index => $value) {
            $x = $padding + ($index / max($count - 1, 1)) * ($width - ($padding * 2));
            $y = $height - $padding - ((($value - $min) / $range) * ($height - ($padding * 2)));
            $coords[] = [round($x, 1), round($y, 1)];
        }

        return match ($type) {
            'bar' => self::barSvg($coords, $width, $height, $padding, $count),
            'area' => self::areaSvg($coords, $width, $height),
            'smooth' => self::smoothSvg($coords, $width, $height),
            default => self::lineSvg($coords),
        };
    }

    /** @param list<array{0: float, 1: float}> $coords */
    private static function lineSvg(array $coords): string
    {
        $path = self::polylinePath($coords);
        $dots = '';
        foreach ($coords as [$x, $y]) {
            $dots .= sprintf('<circle cx="%s" cy="%s" r="2.5" class="ta-stat-spark__dot"/>', $x, $y);
        }

        return sprintf(
            '<svg viewBox="0 0 220 64" preserveAspectRatio="none" class="ta-stat-spark" aria-hidden="true"><path d="%s" class="ta-stat-spark__line"/>%s</svg>',
            $path,
            $dots
        );
    }

    /** @param list<array{0: float, 1: float}> $coords */
    private static function smoothSvg(array $coords, int $width, int $height): string
    {
        $line = self::smoothPath($coords);
        $area = $line.sprintf(' L %s,%s L %s,%s Z', $coords[count($coords) - 1][0], $height, $coords[0][0], $height);

        return sprintf(
            '<svg viewBox="0 0 %d %d" preserveAspectRatio="none" class="ta-stat-spark" aria-hidden="true"><path d="%s" class="ta-stat-spark__area"/><path d="%s" class="ta-stat-spark__line"/></svg>',
            $width,
            $height,
            $area,
            $line
        );
    }

    /** @param list<array{0: float, 1: float}> $coords */
    private static function areaSvg(array $coords, int $width, int $height): string
    {
        $line = self::polylinePath($coords);
        $area = $line.sprintf(' L %s,%s L %s,%s Z', $coords[count($coords) - 1][0], $height, $coords[0][0], $height);

        return sprintf(
            '<svg viewBox="0 0 %d %d" preserveAspectRatio="none" class="ta-stat-spark" aria-hidden="true"><path d="%s" class="ta-stat-spark__area"/><path d="%s" class="ta-stat-spark__line"/></svg>',
            $width,
            $height,
            $area,
            $line
        );
    }

    /**
     * @param  list<array{0: float, 1: float}>  $coords
     */
    private static function barSvg(array $coords, int $width, int $height, int $padding, int $count): string
    {
        $slot = ($width - ($padding * 2)) / max($count, 1);
        $barWidth = max(6.0, $slot * 0.55);
        $bars = '';

        foreach ($coords as [$x, $y]) {
            $bars .= sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" rx="2" class="ta-stat-spark__bar"/>',
                round($x - ($barWidth / 2), 1),
                $y,
                round($barWidth, 1),
                round($height - $padding - $y, 1)
            );
        }

        return sprintf(
            '<svg viewBox="0 0 %d %d" preserveAspectRatio="none" class="ta-stat-spark" aria-hidden="true">%s</svg>',
            $width,
            $height,
            $bars
        );
    }

    /** @param list<array{0: float, 1: float}> $coords */
    private static function polylinePath(array $coords): string
    {
        $parts = array_map(static fn (array $point) => $point[0].','.$point[1], $coords);

        return 'M '.implode(' L ', $parts);
    }

    /** @param list<array{0: float, 1: float}> $coords */
    private static function smoothPath(array $coords): string
    {
        if ($coords === []) {
            return '';
        }

        if (count($coords) === 1) {
            return 'M '.$coords[0][0].','.$coords[0][1];
        }

        $path = 'M '.$coords[0][0].','.$coords[0][1];

        for ($i = 0; $i < count($coords) - 1; $i++) {
            $current = $coords[$i];
            $next = $coords[$i + 1];
            $controlX = ($current[0] + $next[0]) / 2;
            $path .= sprintf(' C %s,%s %s,%s %s,%s', $controlX, $current[1], $controlX, $next[1], $next[0], $next[1]);
        }

        return $path;
    }
}
