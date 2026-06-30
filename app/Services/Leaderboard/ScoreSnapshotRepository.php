<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\Models\GithubScoreSnapshot;
use Carbon\Carbon;

/**
 * Persists periodic contributor-score snapshots so "Rising" can be measured over
 * a fixed window (e.g. the last 7 days) rather than against the previous run.
 */
class ScoreSnapshotRepository
{
    /**
     * Store one snapshot row per login at the given time.
     *
     * @param  array<string, float>  $scoresByLogin
     */
    public function record(array $scoresByLogin, Carbon $at): void
    {
        $rows = [];
        foreach ($scoresByLogin as $login => $score) {
            $rows[] = [
                'login' => $login,
                'contributor_score' => $score,
                'captured_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            GithubScoreSnapshot::insert($chunk);
        }
    }

    /**
     * The contributor score for each login as of the most recent snapshot taken
     * at or before $cutoff. Logins with no snapshot that old are absent (callers
     * treat that as a zero baseline).
     *
     * @return array<string, float>
     */
    public function baselineAsOf(Carbon $cutoff): array
    {
        $baseline = [];

        GithubScoreSnapshot::query()
            ->where('captured_at', '<=', $cutoff)
            ->orderBy('login')
            ->orderBy('captured_at')
            ->each(function (GithubScoreSnapshot $snapshot) use (&$baseline): void {
                // Ordered ascending by time, so the last write per login wins.
                $baseline[$snapshot->login] = (float) $snapshot->contributor_score;
            });

        return $baseline;
    }

    /**
     * Drop snapshots captured before $before to keep the table bounded.
     */
    public function prune(Carbon $before): void
    {
        GithubScoreSnapshot::query()
            ->where('captured_at', '<', $before)
            ->delete();
    }
}
