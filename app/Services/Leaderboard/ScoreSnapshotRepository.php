<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\Models\GithubScoreSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persists periodic contributor-score snapshots so "Rising" can be measured over
 * a fixed window (e.g. the last 7 days) rather than against the previous run.
 */
class ScoreSnapshotRepository
{
    /**
     * Store one snapshot row per login for the UTC day of the given time.
     *
     * The compute runs many times a day, but the rising window is measured in
     * days — so a day holds a single snapshot per login rather than one row per
     * run, which keeps the baseline lookup on a fixed daily grid and the table
     * proportional to the retention horizon.
     *
     * The day keeps each login's *highest* score, not the last one written: a
     * run that reads a partly-imported window scores low, and with one row per
     * day that dip would otherwise be the baseline every later run measures
     * against. For the same reason a login already recorded today is kept even
     * when it is absent from this run.
     *
     * @param  array<string, float>  $scoresByLogin
     */
    public function record(array $scoresByLogin, Carbon $at): void
    {
        if ($scoresByLogin === []) {
            return;
        }

        $day = $at->copy()->utc();
        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        DB::transaction(function () use ($scoresByLogin, $at, $dayStart, $dayEnd): void {
            $scores = $scoresByLogin;

            GithubScoreSnapshot::query()
                ->whereBetween('captured_at', [$dayStart, $dayEnd])
                ->each(function (GithubScoreSnapshot $snapshot) use (&$scores): void {
                    $recorded = (float) $snapshot->contributor_score;

                    $scores[$snapshot->login] = isset($scores[$snapshot->login])
                        ? max($scores[$snapshot->login], $recorded)
                        : $recorded;
                });

            GithubScoreSnapshot::query()
                ->whereBetween('captured_at', [$dayStart, $dayEnd])
                ->delete();

            $rows = [];
            foreach ($scores as $login => $score) {
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
        });
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
