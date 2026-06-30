<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\Leaderboard;

use App\Models\GithubScoreSnapshot;
use App\Services\Leaderboard\ScoreSnapshotRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoreSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function testRecordWritesOneRowPerLogin(): void
    {
        $now = Carbon::now();

        (new ScoreSnapshotRepository)->record(['jane' => 5.0, 'bob' => 2.5], $now);

        $this->assertDatabaseHas(GithubScoreSnapshot::class, ['login' => 'jane', 'contributor_score' => 5.0]);
        $this->assertDatabaseHas(GithubScoreSnapshot::class, ['login' => 'bob', 'contributor_score' => 2.5]);
    }

    public function testBaselineReturnsLatestSnapshotAtOrBeforeCutoff(): void
    {
        $now = Carbon::now();

        // Two snapshots before the cutoff (the later one wins) and one after (ignored).
        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 1.0,
            'captured_at' => $now->copy()->subDays(20),
        ]);
        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 4.0,
            'captured_at' => $now->copy()->subDays(8),
        ]);
        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 9.0,
            'captured_at' => $now->copy()->subDays(1),
        ]);

        $baseline = (new ScoreSnapshotRepository)->baselineAsOf($now->copy()->subDays(7));

        $this->assertSame(4.0, $baseline['jane']);
    }

    public function testBaselineOmitsLoginsWithoutAnOldEnoughSnapshot(): void
    {
        $now = Carbon::now();

        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 9.0,
            'captured_at' => $now->copy()->subDays(1),
        ]);

        $baseline = (new ScoreSnapshotRepository)->baselineAsOf($now->copy()->subDays(7));

        $this->assertArrayNotHasKey('jane', $baseline);
    }

    public function testPruneDropsSnapshotsBeforeTheHorizon(): void
    {
        $now = Carbon::now();

        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 1.0,
            'captured_at' => $now->copy()->subDays(90),
        ]);
        GithubScoreSnapshot::create([
            'login' => 'jane',
            'contributor_score' => 2.0,
            'captured_at' => $now->copy()->subDays(10),
        ]);

        (new ScoreSnapshotRepository)->prune($now->copy()->subDays(60));

        $this->assertSame(1, GithubScoreSnapshot::count());
        $this->assertDatabaseMissing(GithubScoreSnapshot::class, ['contributor_score' => 1.0]);
    }
}
