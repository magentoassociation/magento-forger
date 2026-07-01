<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ContributionItem;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Models\GithubScoreSnapshot;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\OrgLeaderboardEntry;
use App\Models\RoleEligibility;
use App\Models\UserOrgMembership;
use App\Services\Leaderboard\ClaimRecordReader;
use App\Services\Leaderboard\ContributionDetailReader;
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

        $this->mock(ContributionDetailReader::class)
            ->shouldReceive('readForLogin')
            ->andReturn([]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testCommandExitsSuccessfullyWithNoEvents(): void
    {
        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([]);

        $this->artisan('leaderboard:compute')
            ->assertExitCode(0)
            ->expectsOutputToContain('Reading scored events since')
            ->expectsOutputToContain('0 scored events read.');
    }

    public function testCommandWritesLeaderboardEntriesForEachRole(): void
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

    public function testCommandWritesGithubUserStats(): void
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

    public function testCommandAssignsRanksInDescendingScoreOrder(): void
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

    public function testCommandPreservesPreviousScoresForDelta(): void
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

    public function testRisingBaselineComesFromSnapshotAtWindowStart(): void
    {
        config()->set('leaderboard.rising.window_days', 7);

        $now = Carbon::now();

        // A snapshot from before the rising window — the baseline jane should be
        // measured against. A newer (in-window) snapshot must be ignored.
        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 2.0,
            'captured_at' => $now->copy()->subDays(8),
        ]);
        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 99.0,
            'captured_at' => $now->copy()->subDays(1),
        ]);

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $stat = GithubUserStat::where('login', 'jane')->first();
        $this->assertSame(2.0, $stat->rising_baseline_score);

        // Today's run records a fresh snapshot for the next window.
        $this->assertDatabaseHas(GithubScoreSnapshot::class, ['login' => 'jane']);
        $this->assertSame(3, GithubScoreSnapshot::where('login', 'jane')->count());
    }

    public function testCommandPrunesSnapshotsPastRetention(): void
    {
        config()->set('leaderboard.rising.retention_days', 60);

        $now = Carbon::now();

        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 1.0,
            'captured_at' => $now->copy()->subDays(90),
        ]);

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        // The 90-day-old row is gone; only today's fresh snapshot remains.
        $this->assertSame(1, GithubScoreSnapshot::where('login', 'jane')->count());
    }

    public function testCommandEvictsStaleRolling12EntriesForInactiveUsers(): void
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

    public function testCommandWritesMonthlyEntriesWithoutRecencyDecay(): void
    {
        Carbon::setTestNow('2026-07-15T12:00:00Z');

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-07-02T00:00:00Z')),
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-03-02T00:00:00Z')),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        // Both months are within the trailing 12; each pr_opened is worth its
        // full base weight of 3 regardless of age (no decay on monthly boards).
        $july = LeaderboardEntry::where('login', 'jane')->where('board', 'contributor')->where('window', '2026-07')->first();
        $march = LeaderboardEntry::where('login', 'jane')->where('board', 'contributor')->where('window', '2026-03')->first();

        $this->assertNotNull($july);
        $this->assertNotNull($march);
        $this->assertSame(3.0, (float) $july->score);
        $this->assertSame(3.0, (float) $march->score);
    }

    public function testCommandExcludesMonthsOutsideTheTrailingWindow(): void
    {
        Carbon::setTestNow('2026-07-15T12:00:00Z');

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                // 13 months back — outside the trailing 12, so no monthly row.
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2025-06-10T00:00:00Z')),
                new ScoredEvent('jane', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-07-02T00:00:00Z')),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'jane', 'window' => '2026-07']);
        $this->assertDatabaseMissing(LeaderboardEntry::class, ['login' => 'jane', 'window' => '2025-06']);
    }

    public function testCommandRanksMonthlyEntriesPerMonth(): void
    {
        Carbon::setTestNow('2026-07-15T12:00:00Z');

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-07-02T00:00:00Z')),
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-07-03T00:00:00Z')),
                new ScoredEvent('bob', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-07-04T00:00:00Z')),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $alice = LeaderboardEntry::where('login', 'alice')->where('board', 'contributor')->where('window', '2026-07')->first();
        $bob = LeaderboardEntry::where('login', 'bob')->where('board', 'contributor')->where('window', '2026-07')->first();

        $this->assertSame(1, $alice->rank);
        $this->assertSame(2, $bob->rank);
    }

    public function testCommandEvictsStaleMonthlyEntriesButKeepsRolling(): void
    {
        Carbon::setTestNow('2026-07-15T12:00:00Z');

        // A monthly row from a month no longer in the window must be swept away.
        LeaderboardEntry::create([
            'login' => 'ghost',
            'board' => 'contributor',
            'window' => '2020-01',
            'score' => 99.0,
            'computed_at' => Carbon::now(),
        ]);

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse('2026-07-02T00:00:00Z')),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseMissing(LeaderboardEntry::class, ['window' => '2020-01']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'alice', 'window' => 'rolling12']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'alice', 'window' => '2026-07']);
    }

    public function testCommandRecordsComebackAfterLongGap(): void
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

        $this->mock(ContributionDetailReader::class)
            ->shouldReceive('readForLogin')
            ->andReturn([
                new ContributionItem(
                    Board::CONTRIBUTOR,
                    Action::PR_OPENED,
                    $now,
                    1.0,
                    'Welcome back PR',
                    'https://github.com/magento/magento2/pull/999',
                ),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $stat = GithubUserStat::where('login', 'jane')->first();
        $this->assertNotNull($stat->returned_after_days);
        $this->assertGreaterThanOrEqual(365, $stat->returned_after_days);
        $this->assertSame('https://github.com/magento/magento2/pull/999', $stat->comeback_url);
    }

    public function testCommandLinksNewContributorToFirstContribution(): void
    {
        config()->set('leaderboard.spotlight.window_days', 30);

        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('newbie', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        $firstContribution = $this->mock(FirstContributionReader::class);
        $firstContribution->shouldReceive('read')->andReturn(['newbie' => $now->copy()->subDays(3)]);
        $firstContribution->shouldReceive('lastContributionBefore')->andReturn([]);

        $this->mock(ContributionDetailReader::class)
            ->shouldReceive('readForLogin')
            ->andReturn([
                new ContributionItem(
                    Board::CONTRIBUTOR,
                    Action::PR_OPENED,
                    $now->copy()->subDays(3),
                    1.0,
                    'My first PR',
                    'https://github.com/magento/magento2/pull/1',
                ),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $stat = GithubUserStat::where('login', 'newbie')->first();
        $this->assertSame('https://github.com/magento/magento2/pull/1', $stat->first_contribution_url);
        $this->assertSame('My first PR', $stat->first_contribution_title);
    }

    public function testEstablishedContributorHasNoFirstContributionLink(): void
    {
        config()->set('leaderboard.spotlight.window_days', 30);

        $now = Carbon::now();

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                new ScoredEvent('veteran', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
            ]);

        // First contribution is years old → not a newcomer → no link captured.
        $firstContribution = $this->mock(FirstContributionReader::class);
        $firstContribution->shouldReceive('read')->andReturn(['veteran' => $now->copy()->subYears(2)]);
        $firstContribution->shouldReceive('lastContributionBefore')->andReturn([]);

        $this->mock(ContributionDetailReader::class)
            ->shouldReceive('readForLogin')
            ->andReturn([]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertNull(GithubUserStat::where('login', 'veteran')->first()->first_contribution_url);
    }

    public function testRecentContributorIsNotFlaggedAsComeback(): void
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

    public function testGatesMaintainerEventsOnly(): void
    {
        RoleEligibility::create(['login' => 'mod', 'role' => 'maintainer']);
        RoleEligibility::create(['login' => 'councilor', 'role' => 'community-council']);

        $now = Carbon::now();
        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([
                // contributor — always counts
                new ScoredEvent('alice', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
                // contributor — always counts
                new ScoredEvent('bob', Board::CONTRIBUTOR, Action::PR_OPENED, $now),
                // eligible maintainer
                new ScoredEvent('mod', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),
                // council — has maintainer rights
                new ScoredEvent('councilor', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),
                // not a maintainer → filtered
                new ScoredEvent('alice', Board::MAINTAINER, Action::REVIEW_APPROVED, $now),
            ]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        // Contributors are no longer gated — both alice and bob count.
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'alice', 'board' => 'contributor']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'bob', 'board' => 'contributor']);
        $this->assertDatabaseHas(LeaderboardEntry::class, ['login' => 'mod', 'board' => 'maintainer']);

        // Council members hold maintainer rights, so their reviews earn points.
        $this->assertGreaterThan(
            0.0,
            LeaderboardEntry::where('login', 'councilor')->where('board', 'maintainer')->first()->score,
        );

        // alice has no maintainer rights → her maintainer review was filtered.
        $aliceMaintainer = LeaderboardEntry::where('login', 'alice')->where('board', 'maintainer')->first();
        $this->assertSame(0.0, $aliceMaintainer->score);
    }

    public function testCommandWritesCompanyEntriesWithPointInTimeAttribution(): void
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

    public function testCommandClearsStaleOrgEntriesWhenNoCompanies(): void
    {
        $org = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'type' => 'agency']);
        OrgLeaderboardEntry::create([
            'organization_id' => $org->id,
            'board' => 'contributor',
            'window' => 'rolling12',
            'score' => 42.0,
            'member_count' => 1,
            'computed_at' => Carbon::now()->subDay(),
        ]);

        $this->mock(ScoredEventReader::class)
            ->shouldReceive('read')
            ->andReturn([]);

        $this->artisan('leaderboard:compute')->assertExitCode(0);

        $this->assertDatabaseMissing(OrgLeaderboardEntry::class, [
            'organization_id' => $org->id,
            'window' => 'rolling12',
        ]);
    }

    public function testCommandOutputsContributorCount(): void
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
