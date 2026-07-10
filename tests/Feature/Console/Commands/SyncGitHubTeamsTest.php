<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Models\RoleEligibility;
use App\Services\GitHub\GitHubConnection;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncGitHubTeamsTest extends TestCase
{
    use RefreshDatabase;

    public function testTeamSyncActivatesMembersAndMarksLeaversInactiveWithoutDeleting(): void
    {
        // alice already active, erin returning (inactive), carol left the team, dave already inactive.
        RoleEligibility::create(['login' => 'alice', 'role' => 'maintainer', 'active' => true]);
        RoleEligibility::create(['login' => 'erin', 'role' => 'maintainer', 'active' => false]);
        RoleEligibility::create(['login' => 'carol', 'role' => 'maintainer', 'active' => true]);
        RoleEligibility::create(['login' => 'dave', 'role' => 'maintainer', 'active' => false]);

        config([
            'github.repo' => 'magento/magento2',
            'leaderboard.teams.maintainers' => 'the-team',
            'leaderboard.teams.council' => [],
        ]);

        // Team roster from GitHub: alice + erin (bob is a brand-new member).
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                ['login' => 'alice'],
                ['login' => 'erin'],
                ['login' => 'bob'],
            ])),
        ]);
        $rest = new Client(['handler' => HandlerStack::create($mock)]);
        $this->app->instance(
            GitHubConnection::class,
            new GitHubConnection(graphQlClient: new Client, restClient: $rest),
        );

        $this->artisan('sync:github:teams')->assertExitCode(0);

        $this->assertDatabaseHas('role_eligibilities', ['login' => 'alice', 'role' => 'maintainer', 'active' => true]);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'erin', 'role' => 'maintainer', 'active' => true]);   // reactivated
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'bob', 'role' => 'maintainer', 'active' => true]);    // newly added
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'carol', 'role' => 'maintainer', 'active' => false]); // left → inactive, not deleted
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'dave', 'role' => 'maintainer', 'active' => false]);  // stays inactive
    }

    public function testHardCodedCouncilListActivatesMembersAndRetainsLeaversAsInactive(): void
    {
        RoleEligibility::create(['login' => 'stale', 'role' => 'community-council', 'active' => true]);

        config([
            'leaderboard.teams.maintainers' => '',
            'leaderboard.teams.council' => ['ada', 'grace ', '', 'ada', 'linus'],
        ]);

        $this->artisan('sync:github:teams')->assertExitCode(0);

        $this->assertDatabaseHas('role_eligibilities', ['login' => 'ada', 'role' => 'community-council', 'active' => true]);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'grace', 'role' => 'community-council', 'active' => true]);
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'linus', 'role' => 'community-council', 'active' => true]);
        // Removed member is retained but marked inactive, not deleted.
        $this->assertDatabaseHas('role_eligibilities', ['login' => 'stale', 'role' => 'community-council', 'active' => false]);

        // Trimmed, de-duplicated, blanks dropped: three active members.
        $this->assertSame(3, RoleEligibility::where('role', 'community-council')->where('active', true)->count());
    }

    public function testEmptyHardCodedListLeavesRosterUnchanged(): void
    {
        RoleEligibility::create(['login' => 'keep', 'role' => 'community-council']);

        config([
            'leaderboard.teams.maintainers' => '',
            'leaderboard.teams.council' => [],
        ]);

        $this->artisan('sync:github:teams')->assertExitCode(0);

        $this->assertDatabaseHas('role_eligibilities', ['login' => 'keep', 'role' => 'community-council']);
    }
}
