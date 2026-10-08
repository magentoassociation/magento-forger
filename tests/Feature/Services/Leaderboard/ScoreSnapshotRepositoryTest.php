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

    public function testRecordKeepsOneSnapshotPerLoginPerDay(): void
    {
        $repository = new ScoreSnapshotRepository;
        $morning = Carbon::parse('2026-08-12 00:10:00', 'UTC');

        $repository->record(['jane' => 5.0, 'bob' => 2.5], $morning);
        $repository->record(['jane' => 7.0, 'bob' => 3.5], $morning->copy()->addHours(23));

        $this->assertSame(2, GithubScoreSnapshot::whereDate('captured_at', '2026-08-12')->count());
        $this->assertSame(7.0, (float) GithubScoreSnapshot::where('login', 'jane')->value('contributor_score'));
    }

    public function testRecordKeepsTheDaysHighestScorePerLogin(): void
    {
        $repository = new ScoreSnapshotRepository;
        $morning = Carbon::parse('2026-08-12 00:10:00', 'UTC');

        $repository->record(['jane' => 7.0, 'bob' => 2.5], $morning);

        // A later run over a partly-imported window scores lower: the dip must
        // not become the baseline for the day.
        $repository->record(['jane' => 4.0, 'bob' => 3.5], $morning->copy()->addHours(2));

        $this->assertSame(7.0, (float) GithubScoreSnapshot::where('login', 'jane')->value('contributor_score'));
        $this->assertSame(3.5, (float) GithubScoreSnapshot::where('login', 'bob')->value('contributor_score'));
    }

    public function testRecordKeepsLoginsMissingFromALaterRunOfTheSameDay(): void
    {
        $repository = new ScoreSnapshotRepository;
        $morning = Carbon::parse('2026-08-12 00:10:00', 'UTC');

        $repository->record(['jane' => 7.0, 'bob' => 2.5], $morning);
        $repository->record(['jane' => 8.0], $morning->copy()->addHours(2));

        $this->assertSame(2, GithubScoreSnapshot::count());
        $this->assertSame(2.5, (float) GithubScoreSnapshot::where('login', 'bob')->value('contributor_score'));
    }

    public function testRecordLeavesEarlierDaysInPlace(): void
    {
        $repository = new ScoreSnapshotRepository;
        $today = Carbon::parse('2026-08-12 12:00:00', 'UTC');

        $repository->record(['jane' => 5.0], $today->copy()->subDay());
        $repository->record(['jane' => 6.0], $today);

        $this->assertSame(2, GithubScoreSnapshot::count());
    }

    public function testRecordWithoutScoresKeepsTheDaysExistingSnapshot(): void
    {
        $repository = new ScoreSnapshotRepository;
        $now = Carbon::parse('2026-08-12 12:00:00', 'UTC');

        $repository->record(['jane' => 5.0], $now);
        $repository->record([], $now->copy()->addHour());

        $this->assertSame(1, GithubScoreSnapshot::count());
        $this->assertSame(5.0, (float) GithubScoreSnapshot::where('login', 'jane')->value('contributor_score'));
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
