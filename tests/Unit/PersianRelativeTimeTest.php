<?php

namespace Tests\Unit;

use App\Support\PersianRelativeTime;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PersianRelativeTimeTest extends TestCase
{
    public function test_diff_for_humans_returns_persian_text(): void
    {
        $label = PersianRelativeTime::diffForHumans(Carbon::now()->subMinute());

        $this->assertNotNull($label);
        $this->assertStringContainsString('دقیقه', $label);
        $this->assertStringContainsString('پیش', $label);
        $this->assertStringNotContainsString('minute', $label);
        $this->assertStringNotContainsString('ago', $label);
    }

    public function test_diff_for_humans_returns_null_for_missing_moment(): void
    {
        $this->assertNull(PersianRelativeTime::diffForHumans(null));
    }
}
