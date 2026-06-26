<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ContributionItem;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\OrgLeaderboardEntry;
use App\Models\RoleEligibility;
use App\Services\Leaderboard\ContributionDetailReader;
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

    public function test_index_redirects_to_contributor_board(): void
    {
        $this->get(route('scores.index'))
            ->assertRedirect(route('scores.show', ['board' => 'contributor']));
    }

    public function test_invalid_board_returns_404(): void
    {
        $this->get('/scores/nope')->assertNotFound();
    }

    public function test_contributor_board_lists_entries_with_score(): void
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
            ->assertSee('jane')
            ->assertSee('42.5');
    }

    public function test_detail_lists_a_users_contributions(): void
    {
        $this->mock(ContributionDetailReader::class)
            ->shouldReceive('readForLogin')
            ->andReturn([
                new ContributionItem(Board::CONTRIBUTOR, Action::PR_MERGED, now(), 2.0, 'Fix the thing', 'https://github.com/magento/magento2/pull/123'),
            ]);

        $this->get(route('scores.detail', ['board' => 'contributor', 'login' => 'jane']))
            ->assertOk()
            ->assertSee('jane')
            ->assertSee('Fix the thing');
    }

    public function test_detail_rejects_invalid_board(): void
    {
        $this->get('/scores/company/user/jane')->assertNotFound();
    }

    public function test_highlights_shows_each_segment(): void
    {
        GithubUserStat::create(['login' => 'newbie', 'first_contribution_at' => now()->subDays(5), 'contributor_score' => 5, 'computed_at' => now()]);
        GithubUserStat::create(['login' => 'climber', 'contributor_score' => 20, 'contributor_score_prev' => 10, 'computed_at' => now()]);
        GithubUserStat::create(['login' => 'returner', 'returned_after_days' => 400, 'computed_at' => now()]);

        $this->get(route('scores.highlights'))
            ->assertOk()
            ->assertSee('newbie')
            ->assertSee('climber')
            ->assertSee('returner')
            ->assertSee('back after 400 days');
    }

    public function test_board_hides_zero_score_entries(): void
    {
        LeaderboardEntry::create(['login' => 'realmaintainer', 'board' => 'maintainer', 'window' => 'rolling12', 'score' => 12.0, 'rank' => 1, 'computed_at' => now()]);
        LeaderboardEntry::create(['login' => 'justacontributor', 'board' => 'maintainer', 'window' => 'rolling12', 'score' => 0, 'rank' => 2, 'computed_at' => now()]);

        $this->get(route('scores.show', ['board' => 'maintainer']))
            ->assertOk()
            ->assertSee('realmaintainer')
            ->assertDontSee('justacontributor');
    }

    public function test_maintainer_board_shows_full_roster_including_zero_scores(): void
    {
        RoleEligibility::create(['login' => 'idlemaintainer', 'role' => 'maintainer']);
        RoleEligibility::create(['login' => 'activemaintainer', 'role' => 'maintainer']);

        LeaderboardEntry::create(['login' => 'activemaintainer', 'board' => 'maintainer', 'window' => 'rolling12', 'score' => 9.0, 'rank' => 1, 'computed_at' => now()]);
        // Scored, but not on the roster — must not appear.
        LeaderboardEntry::create(['login' => 'outsider', 'board' => 'maintainer', 'window' => 'rolling12', 'score' => 50.0, 'rank' => 2, 'computed_at' => now()]);

        $this->get(route('scores.show', ['board' => 'maintainer']))
            ->assertOk()
            ->assertSee('idlemaintainer')    // on roster, zero score
            ->assertSee('activemaintainer')
            ->assertDontSee('outsider');     // scored but not on roster
    }

    public function test_company_board_merges_org_rows(): void
    {
        $acme = Organization::create(['name' => 'Acme', 'slug' => 'acme', 'type' => 'agency']);
        OrgLeaderboardEntry::create(['organization_id' => $acme->id, 'board' => 'contributor', 'window' => 'rolling12', 'score' => 10.0, 'member_count' => 3, 'rank' => 1, 'computed_at' => now()]);
        OrgLeaderboardEntry::create(['organization_id' => $acme->id, 'board' => 'maintainer', 'window' => 'rolling12', 'score' => 5.0, 'member_count' => 3, 'rank' => 1, 'computed_at' => now()]);

        $this->get(route('scores.show', ['board' => 'company']))
            ->assertOk()
            ->assertSee('Acme');
    }
}
