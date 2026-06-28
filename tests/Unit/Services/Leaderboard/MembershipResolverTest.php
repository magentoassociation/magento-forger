<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\Services\Leaderboard\MembershipResolver;
use Carbon\Carbon;
use Tests\TestCase;

class MembershipResolverTest extends TestCase
{
    public function test_resolves_org_for_date_within_membership(): void
    {
        $resolver = new MembershipResolver([
            'jane' => [['org_id' => 7, 'from' => Carbon::parse('2024-01-01'), 'to' => Carbon::parse('2024-12-31')]],
        ]);

        $this->assertSame(7, $resolver->resolve('jane', Carbon::parse('2024-06-01')));
    }

    public function test_returns_null_outside_membership(): void
    {
        $resolver = new MembershipResolver([
            'jane' => [['org_id' => 7, 'from' => Carbon::parse('2024-01-01'), 'to' => Carbon::parse('2024-12-31')]],
        ]);

        $this->assertNull($resolver->resolve('jane', Carbon::parse('2025-06-01')));
        $this->assertNull($resolver->resolve('jane', Carbon::parse('2023-06-01')));
    }

    public function test_returns_null_for_unknown_login(): void
    {
        $this->assertNull((new MembershipResolver([]))->resolve('ghost', Carbon::parse('2024-06-01')));
    }

    public function test_open_ended_membership_matches_any_later_date(): void
    {
        $resolver = new MembershipResolver([
            'jane' => [['org_id' => 3, 'from' => Carbon::parse('2024-01-01'), 'to' => null]],
        ]);

        $this->assertSame(3, $resolver->resolve('jane', Carbon::parse('2030-01-01')));
    }

    public function test_point_in_time_picks_correct_org_across_job_change(): void
    {
        $resolver = new MembershipResolver([
            'jane' => [
                ['org_id' => 1, 'from' => Carbon::parse('2020-01-01'), 'to' => Carbon::parse('2022-12-31')],
                ['org_id' => 2, 'from' => Carbon::parse('2023-01-01'), 'to' => null],
            ],
        ]);

        $this->assertSame(1, $resolver->resolve('jane', Carbon::parse('2021-06-01')));
        $this->assertSame(2, $resolver->resolve('jane', Carbon::parse('2024-06-01')));
    }

    public function test_overlapping_memberships_prefer_most_recent_start_regardless_of_input_order(): void
    {
        // Older range listed first, but a newer overlapping range should win.
        $resolver = new MembershipResolver([
            'jane' => [
                ['org_id' => 1, 'from' => Carbon::parse('2020-01-01'), 'to' => null],
                ['org_id' => 2, 'from' => Carbon::parse('2024-01-01'), 'to' => null],
            ],
        ]);

        $this->assertSame(2, $resolver->resolve('jane', Carbon::parse('2025-06-01')));
        // Before the newer range starts, the older one still applies.
        $this->assertSame(1, $resolver->resolve('jane', Carbon::parse('2021-06-01')));
    }

    public function test_dated_membership_wins_over_open_ended_profile_suggestion(): void
    {
        // Open-ended suggestion (from/to null) listed first; a dated range covering
        // the same date is more specific and must win.
        $resolver = new MembershipResolver([
            'jane' => [
                ['org_id' => 9, 'from' => null, 'to' => null],
                ['org_id' => 5, 'from' => Carbon::parse('2024-01-01'), 'to' => null],
            ],
        ]);

        $this->assertSame(5, $resolver->resolve('jane', Carbon::parse('2025-06-01')));
        // Outside the dated range, fall back to the open-ended suggestion.
        $this->assertSame(9, $resolver->resolve('jane', Carbon::parse('2000-01-01')));
    }
}
