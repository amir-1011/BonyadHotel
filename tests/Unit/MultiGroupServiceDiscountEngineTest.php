<?php

namespace Tests\Unit;

use App\Services\MultiGroupServiceDiscountEngine;
use App\Services\ServiceDiscountTierEngine;
use PHPUnit\Framework\TestCase;

class MultiGroupServiceDiscountEngineTest extends TestCase
{
    public function test_combines_free_sessions_from_two_groups(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                    => 'veteran_70_spouses',
                'priority'               => 70,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 3,
                'discount_percentage'    => 65,
                'use_tiered_discount'    => false,
            ],
            [
                'key'                    => 'martyr_children',
                'priority'               => 50,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 2,
                'discount_percentage'    => 50,
                'use_tiered_discount'    => false,
            ],
        ]);

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            5,
            $groups,
            [],
            [],
        );

        $this->assertSame(5, $result['free_units']);
        $this->assertSame(2_500_000, $result['discount_amount']);
        $this->assertSame(100, $result['effective_discount_percentage']);
        $this->assertSame(3, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(2, $result['group_usage']['martyr_children']);
    }

    public function test_switches_to_second_group_when_it_offers_better_discount(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                    => 'veteran_70_spouses',
                'priority'               => 70,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 3,
                'discount_percentage'    => 65,
                'use_tiered_discount'    => false,
            ],
            [
                'key'                    => 'martyr_children',
                'priority'               => 50,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 2,
                'discount_percentage'    => 50,
                'use_tiered_discount'    => false,
            ],
        ]);

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            6,
            $groups,
            [],
            [],
        );

        // 5 free + session 6 at 65% from primary group
        $this->assertSame(5, $result['free_units']);
        $this->assertSame(2_825_000, $result['discount_amount']);
        $this->assertSame(175_000, 6 * 500_000 - $result['discount_amount']);
    }

    public function test_tiered_primary_group_with_legacy_secondary_group(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                 => 'veteran_70_spouses',
                'priority'            => 70,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 3],
                    ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
                ],
            ],
            [
                'key'                    => 'martyr_children',
                'priority'               => 50,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 2,
                'discount_percentage'    => 50,
                'use_tiered_discount'    => false,
            ],
        ]);

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            5,
            $groups,
            [],
            [],
        );

        $this->assertSame(5, $result['free_units']);
        $this->assertSame(2_500_000, $result['discount_amount']);
    }

    public function test_weekly_offset_is_per_group(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                    => 'veteran_70_spouses',
                'priority'               => 70,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 3,
                'discount_percentage'    => 65,
                'use_tiered_discount'    => false,
            ],
            [
                'key'                    => 'martyr_children',
                'priority'               => 50,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 2,
                'discount_percentage'    => 50,
                'use_tiered_discount'    => false,
            ],
        ]);

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            2,
            $groups,
            ['veteran_70_spouses' => 3, 'martyr_children' => 0],
            [],
        );

        // Primary exhausted for week; secondary still has 2 free
        $this->assertSame(2, $result['free_units']);
        $this->assertSame(2, $result['group_usage']['martyr_children']);
    }

    public function test_higher_priority_wins_on_equal_discount(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                    => 'group_a',
                'priority'               => 70,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 1,
                'discount_percentage'    => 0,
                'use_tiered_discount'    => false,
            ],
            [
                'key'                    => 'group_b',
                'priority'               => 50,
                'free_sessions_eligible' => true,
                'weekly_free_sessions'   => 1,
                'discount_percentage'    => 0,
                'use_tiered_discount'    => false,
            ],
        ]);

        $result = MultiGroupServiceDiscountEngine::calculateLine(100_000, 1, $groups, [], []);

        $this->assertSame(1, $result['group_usage']['group_a']);
        $this->assertArrayNotHasKey('group_b', $result['group_usage']);
    }

    public function test_both_groups_tiered_combines_free_ladders(): void
    {
        $groups = $this->dualTieredGroups();

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            5,
            $groups,
            [],
            [],
        );

        $this->assertSame(5, $result['free_units']);
        $this->assertSame(2_500_000, $result['discount_amount']);
        $this->assertSame(0, 5 * 500_000 - $result['discount_amount']);
        $this->assertSame(3, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(2, $result['group_usage']['martyr_children']);
    }

    public function test_both_groups_tiered_picks_higher_discount_after_free_tiers(): void
    {
        $groups = $this->dualTieredGroups();

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            8,
            $groups,
            [],
            [],
        );

        // 5 free + 2× primary fixed-pay + 1× secondary fixed-pay (beats primary 65% on session 8)
        $this->assertSame(5, $result['free_units']);
        $this->assertSame(3_650_000, $result['discount_amount']);
        $this->assertSame(350_000, 8 * 500_000 - $result['discount_amount']);
        $this->assertSame(5, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(3, $result['group_usage']['martyr_children']);
    }

    public function test_both_groups_tiered_secondary_fixed_pay_beats_primary_percentage(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                 => 'group_high',
                'priority'            => 70,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 2],
                    ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 40],
                ],
            ],
            [
                'key'                 => 'group_low',
                'priority'            => 50,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 1],
                    ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => null, 'pay_amount' => 50_000],
                ],
            ],
        ]);

        // Sessions 1–2: primary free; session 3: secondary free
        // Session 4: primary 40% (200k) vs secondary fixed 50k (450k discount) → secondary wins
        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            4,
            $groups,
            [],
            [],
        );

        $this->assertSame(3, $result['free_units']);
        $this->assertSame(1_950_000, $result['discount_amount']);
        $this->assertSame(50_000, 4 * 500_000 - $result['discount_amount']);
        $this->assertSame(2, $result['group_usage']['group_high']);
        $this->assertSame(2, $result['group_usage']['group_low']);
    }

    public function test_both_groups_tiered_weekly_offset_is_independent_per_group(): void
    {
        $groups = $this->dualTieredGroups();

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            3,
            $groups,
            ['veteran_70_spouses' => 3, 'martyr_children' => 0],
            [],
        );

        // Primary free tier exhausted for the week; secondary still has 2 free
        $this->assertSame(2, $result['free_units']);
        $this->assertSame(2, $result['group_usage']['martyr_children']);
        $this->assertSame(1, $result['group_usage']['veteran_70_spouses']);
    }

    public function test_both_groups_tiered_breakdown_tags_winning_group(): void
    {
        $groups = $this->dualTieredGroups();

        $result = MultiGroupServiceDiscountEngine::calculateLine(
            500_000,
            6,
            $groups,
            [],
            [],
        );

        $freeBreakdown = collect($result['discount_breakdown'])
            ->where('type', ServiceDiscountTierEngine::TYPE_FREE);

        $this->assertTrue($freeBreakdown->contains(fn ($row) => $row['veteran_group_key'] === 'veteran_70_spouses'));
        $this->assertTrue($freeBreakdown->contains(fn ($row) => $row['veteran_group_key'] === 'martyr_children'));

        $fixedBreakdown = collect($result['discount_breakdown'])
            ->where('type', ServiceDiscountTierEngine::TYPE_FIXED_PAY)
            ->first();

        $this->assertNotNull($fixedBreakdown);
        $this->assertSame('veteran_70_spouses', $fixedBreakdown['veteran_group_key']);
    }

    public function test_both_groups_tiered_each_group_contributes_remaining_free_after_other_exhausted(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                 => 'priority_70',
                'priority'            => 70,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 2],
                    ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 30],
                ],
            ],
            [
                'key'                 => 'priority_50',
                'priority'            => 50,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 2],
                    ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 80],
                ],
            ],
        ]);

        $result = MultiGroupServiceDiscountEngine::calculateLine(100_000, 3, $groups, [], []);

        // Sessions 1–2: primary free (tie → priority). Session 3: secondary free beats primary 30%.
        $this->assertSame(3, $result['free_units']);
        $this->assertSame(300_000, $result['discount_amount']);
        $this->assertSame(0, 3 * 100_000 - $result['discount_amount']);
        $this->assertSame(2, $result['group_usage']['priority_70']);
        $this->assertSame(1, $result['group_usage']['priority_50']);
    }

    public function test_both_groups_tiered_secondary_fixed_pay_beats_primary_percentage_on_last_session(): void
    {
        $groups = $this->dualTieredGroups();

        $result = MultiGroupServiceDiscountEngine::calculateLine(500_000, 8, $groups, [], []);

        $secondaryFixed = collect($result['discount_breakdown'])
            ->where('type', ServiceDiscountTierEngine::TYPE_FIXED_PAY)
            ->where('veteran_group_key', 'martyr_children')
            ->first();

        $this->assertNotNull($secondaryFixed);
        $this->assertSame(1, $secondaryFixed['units']);
        $this->assertSame(150_000, $secondaryFixed['pay_amount']);
    }

    public function test_mashhad_style_50_69_group_only_two_fixed_pay_sessions_not_unlimited(): void
    {
        $groups = MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                 => 'veteran_70_spouses',
                'priority'            => 70,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 3],
                    ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 100_000],
                    ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
                ],
            ],
            [
                'key'                 => 'veteran_50_69_dependents',
                'priority'            => 50,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 1],
                    ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 50_000],
                ],
            ],
        ]);

        $result = MultiGroupServiceDiscountEngine::calculateLine(500_000, 60, $groups, [], []);

        $secondaryFixed = collect($result['discount_breakdown'])
            ->where('type', ServiceDiscountTierEngine::TYPE_FIXED_PAY)
            ->where('veteran_group_key', 'veteran_50_69_dependents');

        $this->assertSame(4, $result['free_units']);
        $this->assertSame(2, $secondaryFixed->sum('units'));
        $this->assertSame(900_000, $secondaryFixed->sum('discount_amount'));
        $this->assertSame(57, $result['group_usage']['veteran_70_spouses']);
        $this->assertSame(3, $result['group_usage']['veteran_50_69_dependents']);
        $this->assertSame(20_600_000, $result['discount_amount']);
        $this->assertSame(9_400_000, 60 * 500_000 - $result['discount_amount']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dualTieredGroups(): array
    {
        return MultiGroupServiceDiscountEngine::normalizeGroups([
            [
                'key'                 => 'veteran_70_spouses',
                'priority'            => 70,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 3],
                    ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 2, 'pay_amount' => 100_000],
                    ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 65],
                ],
            ],
            [
                'key'                 => 'martyr_children',
                'priority'            => 50,
                'use_tiered_discount' => true,
                'discount_tiers'      => [
                    ['type' => ServiceDiscountTierEngine::TYPE_FREE, 'session_count' => 2],
                    ['type' => ServiceDiscountTierEngine::TYPE_FIXED_PAY, 'session_count' => 1, 'pay_amount' => 150_000],
                    ['type' => ServiceDiscountTierEngine::TYPE_PERCENTAGE, 'session_count' => null, 'discount_percentage' => 50],
                ],
            ],
        ]);
    }
}
