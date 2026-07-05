<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\GithubProfile;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Models\LeaderboardLineItem;
use App\Models\Organization;
use App\Models\OrgLeaderboardEntry;
use App\Models\RoleEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreLeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function testIndexRedirectsToContributorBoard(): void
    {
        $this->get(route('scores.index'))
            ->assertRedirect(route('scores.show', ['board' => 'contributor']));
    }

    public function testInvalidBoardReturns404(): void
    {
        $this->get('/scores/nope')->assertNotFound();
    }

    public function testContributorBoardListsEntriesWithScore(): void
    {
        LeaderboardEntry::create([
            'login' => 'jane',
            'board' => 'contributor',
            'window' => 'rolling12',
            'score' => 42.5,
            'breakdown' => ['pr_opened' => ['count' => 2, 'points' => 6.0]],
            'rank' => 1,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.show', ['board' => 'contributor']))
            ->assertOk()
            ->assertSee('Contributor Leaderboard')
            ->assertDontSee('Scores.show')
            ->assertSee('jane')
            ->assertSee('42.5')
            // Breakdown shows proper English, not the raw action key.
            ->assertSee('Opened a PR')
            ->assertDontSee('pr_opened');
    }

    public function testScoringModalRendersConfiguredPointValues(): void
    {
        config()->set('leaderboard.weights.contributor', ['issue_opened' => 1, 'pr_opened' => 17, 'pr_merged' => 10]);

        $this->get(route('scores.show', ['board' => 'contributor']))
            ->assertOk()
            ->assertSee('How are scores tallied?')
            ->assertSee('Opened a PR')
            // Oxford comma before the final "and".
            ->assertSee('Points come from opening issues, opening PRs, and getting a PR merged.')
            ->assertSee('17');
    }

    public function testDetailListsAUsersPersistedLineItems(): void
    {
        LeaderboardLineItem::create([
            'login' => 'jane',
            'board' => 'contributor',
            'action' => 'pr_merged',
            'title' => 'Fix the thing',
            'url' => 'https://github.com/magento/magento2/pull/123',
            'contributed_at' => now(),
            'points' => 20.0,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.detail', ['board' => 'contributor', 'login' => 'jane']))
            ->assertOk()
            ->assertSee('Contributor Contributions')
            ->assertSee('jane')
            ->assertSee('Fix the thing')
            ->assertSee('PR was merged')
            ->assertDontSee('pr_merged');
    }

    public function testDetailTotalReconcilesWithSummedLineItems(): void
    {
        foreach ([['pr_merged', 20.0], ['review_approved', 3.0], ['label_applied', 1.5]] as [$action, $points]) {
            LeaderboardLineItem::create([
                'login' => 'jane',
                'board' => 'maintainer',
                'action' => $action,
                'title' => 'PR #1',
                'url' => 'https://github.com/magento/magento2/pull/1',
                'contributed_at' => now(),
                'points' => $points,
                'computed_at' => now(),
            ]);
        }

        // Only maintainer-board items count toward this board's total (24.5),
        // regardless of request time — points are precomputed, not re-derived.
        LeaderboardLineItem::create([
            'login' => 'jane',
            'board' => 'contributor',
            'action' => 'pr_opened',
            'title' => 'PR #2',
            'url' => 'https://github.com/magento/magento2/pull/2',
            'contributed_at' => now(),
            'points' => 99.0,
            'computed_at' => now(),
        ]);

        $this->travel(40)->days();

        $this->get(route('scores.detail', ['board' => 'maintainer', 'login' => 'jane']))
            ->assertOk()
            ->assertSee('24.5')
            ->assertDontSee('99.0');

        $this->travelBack();
    }

    public function testDetailListsDerivedBonusesThatOldReaderOmitted(): void
    {
        // Labels and claim bonuses now appear as line items (the whole point of
        // persisting them), so the drill-down is complete.
        LeaderboardLineItem::create([
            'login' => 'maint',
            'board' => 'maintainer',
            'action' => 'label_applied',
            'title' => "'bug' label",
            'url' => null,
            'contributed_at' => now(),
            'points' => 1.0,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.detail', ['board' => 'maintainer', 'login' => 'maint']))
            ->assertOk()
            ->assertSee('Applied a triage label')
            ->assertSee("'bug' label");
    }

    public function testDetailShowsPointsBreakdownTooltip(): void
    {
        // pr_merged base is 10. Flat 20 → 2x impact; decayed 11 → 0.55x recency.
        LeaderboardLineItem::create([
            'login' => 'jane',
            'board' => 'contributor',
            'action' => 'pr_merged',
            'title' => 'Fix the thing',
            'url' => 'https://github.com/magento/magento2/pull/123',
            'contributed_at' => now(),
            'points' => 11.0,
            'points_flat' => 20.0,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.detail', ['board' => 'contributor', 'login' => 'jane']))
            ->assertOk()
            ->assertSee('data-bs-toggle="tooltip"', false)
            ->assertSee('10 base × 2× impact × 0.55× recency = 11 pts');
    }

    public function testDetailOmitsTooltipWhenPointsCannotBeDecomposed(): void
    {
        // No flat points persisted (legacy row) → nothing to decompose, no tooltip.
        LeaderboardLineItem::create([
            'login' => 'jane',
            'board' => 'contributor',
            'action' => 'pr_merged',
            'title' => 'Fix the thing',
            'url' => 'https://github.com/magento/magento2/pull/123',
            'contributed_at' => now(),
            'points' => 11.0,
            'points_flat' => 0.0,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.detail', ['board' => 'contributor', 'login' => 'jane']))
            ->assertOk()
            ->assertDontSee('data-bs-title', false);
    }

    public function testDetailRejectsInvalidBoard(): void
    {
        $this->get('/scores/company/user/jane')->assertNotFound();
    }

    public function testHighlightsShowsEachSegment(): void
    {
        GithubUserStat::create([
            'login' => 'newbie',
            'first_contribution_at' => now()->subDays(5),
            'first_contribution_url' => 'https://github.com/magento/magento2/pull/7',
            'contributor_score' => 5,
            'computed_at' => now(),
        ]);
        GithubUserStat::create([
            'login' => 'climber',
            'contributor_score' => 20,
            'rising_baseline_score' => 10,
            'computed_at' => now(),
        ]);
        GithubUserStat::create([
            'login' => 'returner',
            'returned_after_days' => 400,
            'comeback_url' => 'https://github.com/magento/magento2/pull/999',
            'computed_at' => now(),
        ]);

        $this->get(route('scores.highlights'))
            ->assertOk()
            ->assertSee('Leaderboard Highlights')
            ->assertSee('newbie')
            ->assertSee('https://github.com/magento/magento2/pull/7')
            ->assertSee('climber')
            ->assertSee('returner')
            ->assertSee('back after 1 year')
            ->assertSee('https://github.com/magento/magento2/pull/999');
    }

    public function testRecentlyActiveUsesContributorRecencyNotMaintainerActivity(): void
    {
        // rising_baseline_score == score keeps both out of the Rising segment.
        GithubUserStat::create([
            'login' => 'activecontrib',
            'last_contributor_at' => now()->subDays(2),
            'contributor_score' => 8,
            'rising_baseline_score' => 8,
            'computed_at' => now(),
        ]);
        // Recent maintainer work but stale as a contributor → excluded from Recently active.
        GithubUserStat::create([
            'login' => 'recentreviewer',
            'last_contributor_at' => now()->subDays(90),
            'contributor_score' => 8,
            'rising_baseline_score' => 8,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.highlights'))
            ->assertOk()
            ->assertSee('activecontrib')
            ->assertDontSee('recentreviewer');
    }

    public function testBoardShowsRealNameAndHandleWhenProfileExists(): void
    {
        LeaderboardEntry::create([
            'login' => 'janedoe',
            'board' => 'contributor',
            'window' => 'rolling12',
            'score' => 10.0,
            'rank' => 1,
            'computed_at' => now(),
        ]);
        GithubProfile::create([
            'login' => 'janedoe',
            'name' => 'Jane Doe',
            'avatar_url' => 'https://example.com/jane.png',
            'fetched_at' => now(),
        ]);

        $this->get(route('scores.show', ['board' => 'contributor']))
            ->assertOk()
            ->assertSee('Jane Doe')
            ->assertSee('@janedoe');
    }

    public function testBoardHidesZeroScoreEntries(): void
    {
        LeaderboardEntry::create([
            'login' => 'realmaintainer',
            'board' => 'maintainer',
            'window' => 'rolling12',
            'score' => 12.0,
            'rank' => 1,
            'computed_at' => now(),
        ]);
        LeaderboardEntry::create([
            'login' => 'justacontributor',
            'board' => 'maintainer',
            'window' => 'rolling12',
            'score' => 0,
            'rank' => 2,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.show', ['board' => 'maintainer']))
            ->assertOk()
            ->assertSee('realmaintainer')
            ->assertDontSee('justacontributor');
    }

    public function testMaintainerBoardShowsFullRosterIncludingZeroScores(): void
    {
        RoleEligibility::create(['login' => 'idlemaintainer', 'role' => 'maintainer']);
        RoleEligibility::create(['login' => 'activemaintainer', 'role' => 'maintainer']);

        LeaderboardEntry::create([
            'login' => 'activemaintainer',
            'board' => 'maintainer',
            'window' => 'rolling12',
            'score' => 9.0,
            'rank' => 1,
            'computed_at' => now(),
        ]);
        // Scored, but not on the roster — must not appear.
        LeaderboardEntry::create([
            'login' => 'outsider',
            'board' => 'maintainer',
            'window' => 'rolling12',
            'score' => 50.0,
            'rank' => 2,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.show', ['board' => 'maintainer']))
            ->assertOk()
            ->assertSee('idlemaintainer')    // on roster, zero score
            ->assertSee('activemaintainer')
            ->assertDontSee('outsider')      // scored but not on roster
            // Details links only for non-zero scores.
            ->assertSee(route('scores.detail', ['board' => 'maintainer', 'login' => 'activemaintainer']))
            ->assertDontSee(route('scores.detail', ['board' => 'maintainer', 'login' => 'idlemaintainer']));
    }

    public function testMonthlyIndexRedirectsToCurrentMonth(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15T12:00:00Z');

        $this->get(route('scores.monthly.index', ['board' => 'contributor']))
            ->assertRedirect(route('scores.monthly', ['board' => 'contributor', 'ym' => '2026-07']));

        \Carbon\Carbon::setTestNow();
    }

    public function testMonthlyIndexRejectsInvalidBoard(): void
    {
        $this->get('/scores/monthly/company')->assertNotFound();
    }

    public function testMonthlyBoardListsEntriesForTheMonth(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15T12:00:00Z');

        LeaderboardEntry::create([
            'login' => 'jane',
            'board' => 'contributor',
            'window' => '2026-07',
            'score' => 21.0,
            'breakdown' => ['pr_opened' => ['count' => 7, 'points' => 21.0]],
            'rank' => 1,
            'computed_at' => now(),
        ]);
        // A different month's row must not leak into July.
        LeaderboardEntry::create([
            'login' => 'olduser',
            'board' => 'contributor',
            'window' => '2026-06',
            'score' => 99.0,
            'rank' => 1,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.monthly', ['board' => 'contributor', 'ym' => '2026-07']))
            ->assertOk()
            ->assertSee('Contributor Leaderboard — Jul 2026')
            ->assertSee('jane')
            ->assertSee('21')
            ->assertDontSee('olduser')
            // No recency decay copy on monthly boards.
            ->assertDontSee('no longer counts')
            // Each scored row links to its monthly drill-down.
            ->assertSee(route('scores.monthly.detail', ['board' => 'contributor', 'ym' => '2026-07', 'login' => 'jane']));

        \Carbon\Carbon::setTestNow();
    }

    public function testMonthlyBoardOffersMonthNavigation(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15T12:00:00Z');

        $this->get(route('scores.monthly', ['board' => 'contributor', 'ym' => '2026-07']))
            ->assertOk()
            ->assertSee('Jul 2026')
            ->assertSee('Jun 2026')
            ->assertSee(route('scores.monthly', ['board' => 'contributor', 'ym' => '2026-06']));

        \Carbon\Carbon::setTestNow();
    }

    public function testMonthlyBoardRejectsMonthOutsideWindow(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15T12:00:00Z');

        // Too old (more than 12 months back) and in the future both 404.
        $this->get('/scores/monthly/contributor/2020-01')->assertNotFound();
        $this->get('/scores/monthly/contributor/2026-08')->assertNotFound();

        \Carbon\Carbon::setTestNow();
    }

    public function testMonthlyBoardRejectsMalformedMonth(): void
    {
        // Route constraint rejects non-YYYY-MM before the controller runs.
        $this->get('/scores/monthly/contributor/nope')->assertNotFound();
    }

    public function testMonthlyDetailListsMonthItemsAndReconcilesOnFlatPoints(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15T12:00:00Z');

        // July items sum to 13 on flat points (rolling points differ — must be ignored).
        LeaderboardLineItem::create([
            'login' => 'jane', 'board' => 'contributor', 'action' => 'pr_opened',
            'title' => 'Add feature', 'url' => 'https://github.com/magento/magento2/pull/1',
            'contributed_at' => '2026-07-04T00:00:00Z', 'month' => '2026-07',
            'points' => 2.7, 'points_flat' => 3.0, 'computed_at' => now(),
        ]);
        LeaderboardLineItem::create([
            'login' => 'jane', 'board' => 'contributor', 'action' => 'pr_merged',
            'title' => 'Fix bug', 'url' => 'https://github.com/magento/magento2/pull/2',
            'contributed_at' => '2026-07-06T00:00:00Z', 'month' => '2026-07',
            'points' => 9.1, 'points_flat' => 10.0, 'computed_at' => now(),
        ]);
        // A June item must not leak into July.
        LeaderboardLineItem::create([
            'login' => 'jane', 'board' => 'contributor', 'action' => 'pr_opened',
            'title' => 'June work', 'url' => 'https://github.com/magento/magento2/pull/3',
            'contributed_at' => '2026-06-02T00:00:00Z', 'month' => '2026-06',
            'points' => 3.0, 'points_flat' => 3.0, 'computed_at' => now(),
        ]);

        $this->get(route('scores.monthly.detail', ['board' => 'contributor', 'ym' => '2026-07', 'login' => 'jane']))
            ->assertOk()
            ->assertSee('Contributor Contributions — Jul 2026')
            ->assertSee('Add feature')
            ->assertSee('Fix bug')
            ->assertDontSee('June work')
            ->assertSee('13.0');   // flat total, not the rolling 11.8

        \Carbon\Carbon::setTestNow();
    }

    public function testMonthlyDetailRejectsInvalidBoardAndOutOfWindowMonth(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-15T12:00:00Z');

        $this->get('/scores/monthly/company/2026-07/user/jane')->assertNotFound();
        $this->get('/scores/monthly/contributor/2020-01/user/jane')->assertNotFound();
        $this->get('/scores/monthly/contributor/2026-08/user/jane')->assertNotFound();

        \Carbon\Carbon::setTestNow();
    }

    public function testCompanyBoardMergesOrgRows(): void
    {
        $acme = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'type' => 'agency']);
        OrgLeaderboardEntry::create([
            'organization_id' => $acme->id,
            'board' => 'contributor',
            'window' => 'rolling12',
            'score' => 10.0,
            'member_count' => 3,
            'rank' => 1,
            'computed_at' => now(),
        ]);
        OrgLeaderboardEntry::create([
            'organization_id' => $acme->id,
            'board' => 'maintainer',
            'window' => 'rolling12',
            'score' => 5.0,
            'member_count' => 3,
            'rank' => 1,
            'computed_at' => now(),
        ]);

        $this->get(route('scores.show', ['board' => 'company']))
            ->assertOk()
            ->assertSee('Acme');
    }
}
