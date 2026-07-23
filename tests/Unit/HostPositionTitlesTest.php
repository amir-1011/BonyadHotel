<?php

namespace Tests\Unit;

use App\Models\HostPositionTitle;
use App\Support\HostPositionTitles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostPositionTitlesTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_are_seeded_in_catalog(): void
    {
        $this->assertDatabaseHas('host_position_titles', ['label' => 'مدیر مالی', 'is_system' => true]);
        $this->assertContains('کارشناس پشتیبانی', HostPositionTitles::options());
    }

    public function test_remember_persists_custom_title(): void
    {
        $label = HostPositionTitles::remember('سمت ویژه');

        $this->assertSame('سمت ویژه', $label);
        $this->assertDatabaseHas('host_position_titles', ['label' => 'سمت ویژه', 'is_system' => false]);
        $this->assertContains('سمت ویژه', HostPositionTitle::optionLabels());
    }

    public function test_resolve_preset(): void
    {
        $this->assertSame('مدیر داخلی', HostPositionTitles::resolve('مدیر داخلی'));
        $this->assertNull(HostPositionTitles::resolve(''));
    }
}
