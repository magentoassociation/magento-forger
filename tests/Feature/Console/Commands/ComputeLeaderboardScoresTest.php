<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\OrgLeaderboardEntry;
use App\Models\RoleEligibility;
use App\Models\UserOrgMembership;
use App\Services\Leaderboard\ClaimRecordReader;
use App\Services\Leaderboard\FirstContributionReader;
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

        // Keep the suite hermetic: the latency reader hits OpenSearch, which is
        // not part of these sqlite-backed tests. Individual tests drive events
        // through the ScoredEventReader mock.
        $this->mock(ClaimRecordReader::class)
            ->shouldReceive('read')
            ->andReturn([]);

        $firstContribution = $this->mock(FirstContributionReader::class);
        $firstContribution->shouldReceive('read')->andReturn([]);
        $firstContribution->shouldReceive('lastContributionBefore')->andReturn([]);
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
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
                new ScoredEvent('jane', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),
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
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
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
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
                new ScoredEvent('bob', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
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
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
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
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseMissing(LeaderboardEntry::class, ['login' => 'ghost']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'alice']);
    }

    public function test_command_records_comeback_after_long_gap(): void
    {
        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        $firstContribution = $this->mock(FirstContributionReader::class);
        $firstContribution->shouldReceive('read')->andReturn([]);
        $firstContribution->shouldReceive('lastContributionBefore')->andReturn([
            'jane' => $now->copy()->subYears(3),
        ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $stat = GithubUserStat::where('login', 'jane')->first();
        $this->assertNotNull($stat->returned_after_days);
        $this->assertGreaterThanOrEqual(365, $stat->returned_after_days);
    }

    public function test_recent_contributor_is_not_flagged_as_comeback(): void
    {
        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        $firstContribution = $this->mock(FirstContributionReader::class);
        $firstContribution->shouldReceive('read')->andReturn([]);
        $firstContribution->shouldReceive('lastContributionBefore')->andReturn([
            'jane' => $now->copy()->subDays(10),
        ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertNull(GithubUserStat::where('login', 'jane')->first()->returned_after_days);
    }

    public function test_gates_maintainer_events_only(): void
    {
        RoleEligibility::create(['login' => 'mod', 'role' => 'maintainer']);
        RoleEligibility::create(['login' => 'councilor', 'role' => 'community-council']);

        $now = Carbon::now();
        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, $now),         // contributor — always counts
                new ScoredEvent('bob', Board::CONTRIBUTOR, Action::PR_OPENED, $now),           // contributor — always counts
                new ScoredEvent('mod', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),      // eligible maintainer
                new ScoredEvent('councilor', Board::MAINTAINER, Action::REVIEW_APPROVED, $now), // council — has maintainer rights
                new ScoredEvent('alice', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),    // not a maintainer → filtered
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        // Contributors are no longer gated — both alice and bob count.
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'alice', 'board' => 'contributor']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'bob', 'board' => 'contributor']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'mod', 'board' => 'maintainer']);

        // Council members hold maintainer rights, so their reviews earn points.
        $this->assertGreaterThan(0.0, LeaderboardEntry::where('login', 'councilor')->where('board', 'maintainer')->first()->score);

        // alice has no maintainer rights → her maintainer review was filtered.
        $aliceMaintainer = LeaderboardEntry::where('login', 'alice')->where('board', 'maintainer')->first();
        $this->assertSame(0.0, $aliceMaintainer->score);
    }

    public function test_command_writes_company_entries_with_point_in_time_attribution(): void
    {
        $now = Carbon::now();

        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'type' => 'agency']);
        UserOrgMembership::create([
            'login' => 'jane',
            'organization_id' => $org->id,
            'from_date' => $now->copy()->subYears(2),
            'to_date' => null,
            'source' => 'manual',
            'confidence' => 100,
        ]);

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),   // → Acme
                new ScoredEvent('ghost', Board::CONTRIBUTOR, Action::PR_OPENED, $now),  // → Unknown
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseHas(OrgLeaderboardEntry::class, [
            'organization_id' => $org->id,
            'board' => 'contributor',
            'window' => 'rolling12',
        ]);

        $unknown = Organization::where('slug', 'unknown')->first();
        $this->assertNotNull($unknown);
        $this->assertDatabaseHas(OrgLeaderboardEntry::class, [
            'organization_id' => $unknown->id,
            'board' => 'contributor',
        ]);
    }

    public function test_command_outputs_contributor_count(): void
    {
        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
                new ScoredEvent('bob', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        $this->artisan('leaderboard:compute')
            ->assertExitCode(0)
            ->expectsOutputToContain('Leaderboard scores computed for 2 contributors.');
    }
}
