<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\DataTransferObjects\Leaderboard\Board;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Services\Leaderboard\ClaimRecordReader;
use App\Services\Leaderboard\LeaderboardScorer;
use App\Services\Leaderboard\ReviewLatencyAnalyzer;
use App\Services\Leaderboard\ScoredEventReader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;

class ComputeLeaderboardScores extends Command implements Isolatable
{
    protected $signature = 'leaderboard:compute';

    protected $description = 'Compute weighted contributor/maintainer scores and engagement signals from OpenSearch into the leaderboard tables.';

    public function handle(ScoredEventReader $reader, ClaimRecordReader $claimReader, ReviewLatencyAnalyzer $analyzer): int
    {
        $now = Carbon::now();
        $windowDays = (int) config('leaderboard.recency.window_days', 365);
        $from = $now->copy()->subDays($windowDays);

        $this->info("Reading scored events since {$from->toDateString()} ...");
        $events = $reader->read($from, $now);

        $latency = $analyzer->analyze($claimReader->read($from, $now));
        $events = array_merge($events, $latency['events']);
        $latencyStats = $latency['stats'];
        $this->info(count($events).' scored events read.');

        $summary = LeaderboardScorer::fromConfig()->summarize($events, $now);

        // Preserve previous current scores so "Rising" can compute a delta.
        $previousContributor = GithubUserStat::query()->pluck('contributor_score', 'login')->all();
        $previousMaintainer = GithubUserStat::query()->pluck('maintainer_score', 'login')->all();

        $statRows = [];
        $entryRows = [];

        foreach ($summary as $login => $data) {
            $statRows[] = [
                'login' => $login,
                'first_contribution_at' => $data['first_contribution_at'],
                'last_contribution_at' => $data['last_contribution_at'],
                'current_gap_days' => $data['current_gap_days'],
                'current_streak_weeks' => $data['current_streak_weeks'],
                'longest_streak_weeks' => $data['longest_streak_weeks'],
                'contributor_score' => $data['contributor_score'],
                'maintainer_score' => $data['maintainer_score'],
                'contributor_score_prev' => $previousContributor[$login] ?? 0,
                'maintainer_score_prev' => $previousMaintainer[$login] ?? 0,
                'median_time_to_review_hours' => $latencyStats[$login]['median_time_to_review_hours'] ?? null,
                'median_time_to_claim_days' => $latencyStats[$login]['median_time_to_claim_days'] ?? null,
                'reviews_in_window' => $latencyStats[$login]['reviews_in_window'] ?? 0,
                'computed_at' => $now,
            ];

            foreach (Board::cases() as $board) {
                $entryRows[] = [
                    'login' => $login,
                    'board' => $board->value,
                    'window' => 'rolling12',
                    'score' => $data[$board->value.'_score'],
                    'breakdown' => json_encode($data['breakdown'][$board->value] ?? [], JSON_THROW_ON_ERROR),
                    'computed_at' => $now,
                ];
            }
        }

        if ($statRows !== []) {
            GithubUserStat::upsert($statRows, ['login'], [
                'first_contribution_at', 'last_contribution_at', 'current_gap_days',
                'current_streak_weeks', 'longest_streak_weeks', 'contributor_score',
                'maintainer_score', 'contributor_score_prev', 'maintainer_score_prev',
                'median_time_to_review_hours', 'median_time_to_claim_days', 'reviews_in_window', 'computed_at',
            ]);
        }

        if ($entryRows !== []) {
            LeaderboardEntry::query()
                ->where('window', 'rolling12')
                ->whereNotIn('login', array_keys($summary))
                ->delete();

            LeaderboardEntry::upsert($entryRows, ['login', 'board', 'window'], [
                'score', 'breakdown', 'computed_at',
            ]);
        }

        $this->assignRanks();

        $this->info('Leaderboard scores computed for '.count($summary).' contributors.');

        return self::SUCCESS;
    }

    /**
     * Rank rows per board in a fixed number of queries (one ordered read + one
     * bulk upsert per board), rather than one UPDATE per contributor. Uses a
     * batch upsert instead of a ROW_NUMBER() window UPDATE to stay portable
     * across MySQL (production) and SQLite (tests).
     */
    private function assignRanks(): void
    {
        foreach (Board::cases() as $board) {
            $orderedLogins = LeaderboardEntry::query()
                ->where('board', $board->value)
                ->where('window', 'rolling12')
                ->orderByDesc('score')
                ->orderBy('id')
                ->pluck('login');

            $rank = 0;
            $rows = [];
            foreach ($orderedLogins as $login) {
                $rows[] = [
                    'login' => $login,
                    'board' => $board->value,
                    'window' => 'rolling12',
                    'rank' => ++$rank,
                ];
            }

            if ($rows !== []) {
                LeaderboardEntry::upsert($rows, ['login', 'board', 'window'], ['rank']);
            }
        }
    }
}
