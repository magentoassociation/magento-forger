<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Services\Leaderboard\ScoredEventReader;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputeLeaderboardScoresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('leaderboard.recency.window_days', 365);
        config()->set('leaderboard.recency.half_life_days', 182);
        config()->set('leaderboard.weights.contributor', ['pr_opened' => 3, 'pr_merged' => 10]);
        config()->set('leaderboard.weights.maintainer', ['review_approved' => 3]);
        config()->set('leaderboard.impact.min', 1.0);
        config()->set('leaderboard.impact.max', 5.0);
    }

    public function test_command_exits_successfully_with_no_events(): void
    {
        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([]);

        $this->artisan('leaderboard:compute')
            ->assertExitCode(0)
            ->expectsOutputToContain('Reading scored events since')
            ->expectsOutputToContain('0 scored events read.');
    }

    public function test_command_writes_leaderboard_entries_for_each_role(): void
    {
        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now),
                new ScoredEvent('jane', Board::MAINTAINER, 'review_approved', $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseHas(LeaderboardEntry::class, [
            'login' => 'jane',
            'board' => 'contributor',
            'window' => 'rolling12',
        ]);

        $this->assertDatabaseHas(LeaderboardEntry::class, [
            'login' => 'jane',
            'board' => 'maintainer',
            'window' => 'rolling12',
        ]);
    }

    public function test_command_writes_github_user_stats(): void
    {
        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseHas(GithubUserStat::class, ['login' => 'jane']);

        $stat = GithubUserStat::where('login', 'jane')->first();
        $this->assertGreaterThan(0, $stat->contributor_score);
    }

    public function test_command_assigns_ranks_in_descending_score_order(): void
    {
        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('alice', Board::CONTRIBUTOR, 'pr_opened', $now),
                new ScoredEvent('alice', Board::CONTRIBUTOR, 'pr_opened', $now),
                new ScoredEvent('bob', Board::CONTRIBUTOR, 'pr_opened', $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $alice = LeaderboardEntry::where('login', 'alice')->where('board', 'contributor')->first();
        $bob = LeaderboardEntry::where('login', 'bob')->where('board', 'contributor')->first();

        $this->assertSame(1, $alice->rank);
        $this->assertSame(2, $bob->rank);
    }

    public function test_command_preserves_previous_scores_for_delta(): void
    {
        $now = Carbon::now();

        GithubUserStat::create([
            'login' => 'jane',
            'contributor_score' => 15.0,
            'maintainer_score' => 5.0,
            'contributor_score_prev' => 0.0,
            'maintainer_score_prev' => 0.0,
            'first_contribution_at' => $now,
            'last_contribution_at' => $now,
            'current_gap_days' => 0,
            'current_streak_weeks' => 1,
            'longest_streak_weeks' => 1,
            'computed_at' => $now,
        ]);

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, 'pr_opened', $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $stat = GithubUserStat::where('login', 'jane')->first();
        $this->assertSame(15.0, $stat->contributor_score_prev);
    }

    public function test_command_evicts_stale_rolling12_entries_for_inactive_users(): void
    {
        $now = Carbon::now();

        LeaderboardEntry::create([
            'login' => 'ghost',
            'board' => 'contributor',
            'window' => 'rolling12',
            'score' => 99.0,
            'computed_at' => $now,
        ]);

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('alice', Board::CONTRIBUTOR, 'pr_opened', $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseMissing(LeaderboardEntry::class, ['login' => 'ghost']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'alice']);
    }

    public function test_command_outputs_contributor_count(): void
    {
        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('alice', Board::CONTRIBUTOR, 'pr_opened', $now),
                new ScoredEvent('bob', Board::CONTRIBUTOR, 'pr_opened', $now),
            ]);

        $this->artisan('leaderboard:compute')
            ->assertExitCode(0)
            ->expectsOutputToContain('Leaderboard scores computed for 2 contributors.');
    }
}
