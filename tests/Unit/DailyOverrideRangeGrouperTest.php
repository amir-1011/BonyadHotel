<?php

namespace Tests\Unit;

use App\Models\RoomTypeDailyOverride;
use App\Support\DailyOverrideRangeGrouper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DailyOverrideRangeGrouperTest extends TestCase
{
    public function test_groups_consecutive_days_with_same_settings(): void
    {
        $overrides = collect([
            $this->makeOverride(1, '2026-08-01', 5, null, 20, null),
            $this->makeOverride(2, '2026-08-02', 5, null, 20, null),
            $this->makeOverride(3, '2026-08-03', 5, null, 20, null),
        ]);

        $ranges = DailyOverrideRangeGrouper::group($overrides, []);

        $this->assertCount(1, $ranges);
        $this->assertSame('2026-08-01', $ranges[0]['date_from']);
        $this->assertSame('2026-08-03', $ranges[0]['date_to']);
        $this->assertSame(3, $ranges[0]['days_count']);
    }

    public function test_splits_when_settings_change(): void
    {
        $overrides = collect([
            $this->makeOverride(1, '2026-08-01', 5, null, 20, null),
            $this->makeOverride(2, '2026-08-02', 5, null, 30, null),
            $this->makeOverride(3, '2026-08-03', 5, null, 30, null),
        ]);

        $ranges = DailyOverrideRangeGrouper::group($overrides, []);

        $this->assertCount(2, $ranges);
        $this->assertSame('2026-08-01', $ranges[1]['date_from']);
        $this->assertSame('2026-08-02', $ranges[0]['date_from']);
        $this->assertSame('2026-08-03', $ranges[0]['date_to']);
    }

    public function test_splits_non_consecutive_days(): void
    {
        $overrides = collect([
            $this->makeOverride(1, '2026-08-01', 5, null, 20, null),
            $this->makeOverride(2, '2026-08-03', 5, null, 20, null),
        ]);

        $ranges = DailyOverrideRangeGrouper::group($overrides, []);

        $this->assertCount(2, $ranges);
        $this->assertSame(1, $ranges[0]['days_count']);
        $this->assertSame(1, $ranges[1]['days_count']);
    }

    private function makeOverride(int $id, string $date, int $count, ?int $custom, ?int $disc, ?string $label): RoomTypeDailyOverride
    {
        $ov = new RoomTypeDailyOverride([
            'available_count'     => $count,
            'custom_price'        => $custom,
            'discount_percentage' => $disc,
            'price_label'         => $label,
            'reason'              => null,
        ]);
        $ov->id = $id;
        $ov->date = Carbon::parse($date);

        return $ov;
    }
}
