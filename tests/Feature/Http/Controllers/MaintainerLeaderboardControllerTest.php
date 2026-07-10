<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\DataTransferObjects\Dashboard\ContributorCount;
use App\Models\RoleEligibility;
use App\Queries\Dashboard\ReviewsApprovedLeaderboardQuery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintainerLeaderboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testInactiveMaintainerIsBadgedOnTheLeaderboard(): void
    {
        RoleEligibility::create(['login' => 'carol', 'role' => 'maintainer', 'active' => false]);
        RoleEligibility::create(['login' => 'alice', 'role' => 'maintainer', 'active' => true]);

        $this->app->bind(ReviewsApprovedLeaderboardQuery::class, fn () => new class extends ReviewsApprovedLeaderboardQuery
        {
            public function __construct() {}

            public function execute(Carbon $from, Carbon $to): array
            {
                return [
                    new ContributorCount('carol', 5),
                    new ContributorCount('alice', 3),
                ];
            }
        });

        $response = $this->get(route('maintainer.leaderboard.show', ['metric' => 'reviews-approved']));

        $response->assertOk();
        $response->assertSeeInOrder(['carol', 'Inactive']);
        $response->assertSeeText('alice');
        // Only the inactive maintainer is badged.
        $this->assertSame(1, substr_count($response->getContent(), '>Inactive<'));
    }
}
