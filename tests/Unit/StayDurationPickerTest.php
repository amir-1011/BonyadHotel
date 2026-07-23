<?php

namespace Tests\Unit;

use App\Support\StayDurationPicker;
use PHPUnit\Framework\TestCase;

class StayDurationPickerTest extends TestCase
{
    public function test_check_out_from_nights_adds_days(): void
    {
        $this->assertSame('2026-07-18', StayDurationPicker::checkOutFromNights('2026-07-15', 3));
        $this->assertSame('2026-07-16', StayDurationPicker::checkOutFromNights('2026-07-15', 1));
    }

    public function test_nights_between_matches_check_out(): void
    {
        $checkIn = '2026-07-15';
        $nights = 4;
        $checkOut = StayDurationPicker::checkOutFromNights($checkIn, $nights);

        $this->assertSame($nights, StayDurationPicker::nightsBetween($checkIn, $checkOut));
    }

    public function test_last_stay_night_is_day_before_check_out(): void
    {
        $checkIn = '2026-07-15';
        $checkOut = '2026-07-18';

        $this->assertSame('2026-07-17', StayDurationPicker::lastStayNight($checkIn, $checkOut));
    }

    public function test_has_invalid_night_in_range_detects_blocked_middle(): void
    {
        $blocked = ['2026-07-16' => true];

        $invalid = StayDurationPicker::hasInvalidNightInRange(
            '2026-07-15',
            '2026-07-17',
            fn (string $date) => !empty($blocked[$date]),
        );

        $this->assertTrue($invalid);
    }

    public function test_has_invalid_night_in_range_passes_when_all_clear(): void
    {
        $invalid = StayDurationPicker::hasInvalidNightInRange(
            '2026-07-15',
            '2026-07-17',
            fn () => false,
        );

        $this->assertFalse($invalid);
    }

    public function test_validate_nights_input_rejects_invalid_values(): void
    {
        [$validZero, $msgZero] = StayDurationPicker::validateNightsInput(0);
        $this->assertFalse($validZero);
        $this->assertNotEmpty($msgZero);

        [$validOver, $msgOver] = StayDurationPicker::validateNightsInput(StayDurationPicker::MAX_NIGHTS + 1);
        $this->assertFalse($validOver);
        $this->assertNotEmpty($msgOver);
    }

    public function test_validate_nights_input_accepts_valid_range(): void
    {
        [$valid, $msg] = StayDurationPicker::validateNightsInput(3);

        $this->assertTrue($valid);
        $this->assertNull($msg);
    }
}
