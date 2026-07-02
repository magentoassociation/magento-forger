<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Models\Organization;
use App\Models\OrgLeaderboardEntry;
use App\Services\Leaderboard\ClaimRecordReader;
use App\Services\Leaderboard\CompanyScoreAggregator;
use App\Services\Leaderboard\ContributionDetailReader;
use App\Services\Leaderboard\EligibilityGate;
use App\Services\Leaderboard\FirstContributionReader;
use App\Services\Leaderboard\LeaderboardScorer;
use App\Services\Leaderboard\MembershipResolver;
use App\Services\Leaderboard\ReviewLatencyAnalyzer;
use App\Services\Leaderboard\ScoredEventReader;
use App\Services\Leaderboard\ScoreSnapshotRepository;
use App\Support\MonthlyWindow;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\DB;

class ComputeLeaderboardScores extends Command implements Isolatable
{
    protected $signature = 'leaderboard:compute';

    protected $description = 'Compute weighted contributor/maintainer scores and engagement signals '
        .'from OpenSearch into the leaderboard tables.';

    public function handle(
        ScoredEventReader $reader,
        ClaimRecordReader $claimReader,
        ReviewLatencyAnalyzer $analyzer,
        FirstContributionReader $firstContributionReader,
        ContributionDetailReader $detailReader,
        ScoreSnapshotRepository $snapshots,
    ): int {
        $now = Carbon::now();
        $windowDays = (int) config('leaderboard.recency.window_days', 365);
        $from = $now->copy()->subDays($windowDays);

        $this->info("Reading scored events since {$from->toDateString()} ...");
        $events = $reader->read($from, $now);

        $latency = $analyzer->analyze($claimReader->read($from, $now));
        $events = array_merge($events, $latency['events']);
        $latencyStats = $latency['stats'];

        // Gate by GitHub team membership: contributor events for Community
        // Contributors, maintainer events for Community Council (empty roster = allow all).
        $gate = EligibilityGate::fromDatabase();
        $events = array_values(array_filter($events, fn (ScoredEvent $event): bool => $gate->allows($event)));

        $this->info(count($events).' scored events read.');

        $scorer = LeaderboardScorer::fromConfig();
        $summary = $scorer->summarize($events, $now);

        // All-time earliest contribution per author (overrides the windowed value).
        $allTimeFirst = $firstContributionReader->read();

        // Comeback detection: last contribution before the window vs. the return inside it.
        $lastBeforeWindow = $firstContributionReader->lastContributionBefore($from);
        $comebackMinGap = (int) config('leaderboard.comeback.min_gap_days', 365);

        // Preserve previous current scores (per-run delta, kept for reference).
        $previousContributor = GithubUserStat::query()->pluck('contributor_score', 'login')->all();
        $previousMaintainer = GithubUserStat::query()->pluck('maintainer_score', 'login')->all();

        // "Rising" baseline: each contributor's score as of the start of the
        // rising window, read from snapshots before today's snapshot is taken.
        $risingWindowDays = (int) config('leaderboard.rising.window_days', 7);
        $risingBaseline = $snapshots->baselineAsOf($now->copy()->subDays($risingWindowDays));

        // New contributors: first-ever contribution within this window.
        $spotlightCutoff = $now->copy()->subDays((int) config('leaderboard.spotlight.window_days', 30));

        $statRows = [];
        $entryRows = [];

        foreach ($summary as $login => $data) {
            $firstContributionAt = $allTimeFirst[$login] ?? $data['first_contribution_at'];

            $comebackGap = isset($lastBeforeWindow[$login])
                ? (int) abs($lastBeforeWindow[$login]->diffInDays($data['first_contribution_at']))
                : null;
            $isComeback = $comebackGap !== null && $comebackGap >= $comebackMinGap;
            $isNewContributor = $firstContributionAt !== null
                && $firstContributionAt->greaterThanOrEqualTo($spotlightCutoff);

            // Earliest in-window item: the contribution that ended a comeback's
            // silence and, for a newcomer, their first-ever contribution. Fetched
            // once, only when a card actually needs the link.
            $earliest = ($isComeback || $isNewContributor)
                ? collect($detailReader->readForLogin($login, $from, $now))
                    ->sortBy(fn ($item) => $item->date->getTimestamp())
                    ->first()
                : null;

            $returnedAfterDays = $isComeback ? $comebackGap : null;
            $comebackUrl = $isComeback ? $earliest?->url : null;
            $comebackTitle = $isComeback ? $earliest?->title : null;
            $firstContributionUrl = $isNewContributor ? $earliest?->url : null;
            $firstContributionTitle = $isNewContributor ? $earliest?->title : null;

            $statRows[] = [
                'login' => $login,
                'first_contribution_at' => $firstContributionAt,
                'first_contribution_url' => $firstContributionUrl,
                'first_contribution_title' => $firstContributionTitle,
                'last_contribution_at' => $data['last_contribution_at'],
                'last_contributor_at' => $data['last_contributor_at'],
                'current_gap_days' => $data['current_gap_days'],
                'current_streak_weeks' => $data['current_streak_weeks'],
                'longest_streak_weeks' => $data['longest_streak_weeks'],
                'contributor_score' => $data['contributor_score'],
                'maintainer_score' => $data['maintainer_score'],
                'contributor_score_prev' => $previousContributor[$login] ?? 0,
                'maintainer_score_prev' => $previousMaintainer[$login] ?? 0,
                'rising_baseline_score' => $risingBaseline[$login] ?? 0,
                'median_time_to_review_hours' => $latencyStats[$login]['median_time_to_review_hours'] ?? null,
                'median_time_to_claim_days' => $latencyStats[$login]['median_time_to_claim_days'] ?? null,
                'reviews_in_window' => $latencyStats[$login]['reviews_in_window'] ?? 0,
                'returned_after_days' => $returnedAfterDays,
                'comeback_url' => $comebackUrl,
                'comeback_title' => $comebackTitle,
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
                'first_contribution_at', 'first_contribution_url', 'first_contribution_title',
                'last_contribution_at', 'last_contributor_at', 'current_gap_days',
                'current_streak_weeks', 'longest_streak_weeks', 'contributor_score',
                'maintainer_score', 'contributor_score_prev', 'maintainer_score_prev', 'rising_baseline_score',
                'median_time_to_review_hours', 'median_time_to_claim_days', 'reviews_in_window',
                'returned_after_days', 'comeback_url', 'comeback_title', 'computed_at',
            ]);

            // Record today's snapshot so future runs can measure the rising
            // window against it, then drop snapshots past the retention horizon.
            $snapshots->record(
                array_column($statRows, 'contributor_score', 'login'),
                $now,
            );
            $snapshots->prune($now->copy()->subDays((int) config('leaderboard.rising.retention_days', 60)));
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

        // Per-calendar-month boards for the trailing window. Recompute every
        // allowed month in full each run: same gated events, bucketed by UTC
        // month with impact but no recency decay.
        $allowedMonths = MonthlyWindow::allowed();
        $this->writeMonthlyEntries($scorer->summarizeByMonth($events, $allowedMonths), $allowedMonths, $now);

        $this->assignRanks($allowedMonths);

        $this->computeCompanyScores($events, $now);

        $this->info('Leaderboard scores computed for '.count($summary).' contributors.');

        return self::SUCCESS;
    }

    /**
     * Replace all monthly (window != 'rolling12') entries with a fresh set for
     * the allowed months. Recomputing every month in full each run keeps the
     * eviction trivial: delete all monthly rows, then insert the current set, so
     * rolled-off months and contributors who dropped out of a month both vanish
     * without per-row bookkeeping. Never touches the rolling12 rows.
     *
     * @param  array<string, array<string, array{contributor_score: float, maintainer_score: float, breakdown: array<string, mixed>}>>  $byMonth
     * @param  list<string>  $allowedMonths
     */
    private function writeMonthlyEntries(array $byMonth, array $allowedMonths, Carbon $now): void
    {
        DB::transaction(function () use ($byMonth, $allowedMonths, $now): void {
            LeaderboardEntry::query()->where('window', '!=', 'rolling12')->delete();

            $rows = [];
            foreach ($allowedMonths as $month) {
                foreach ($byMonth[$month] ?? [] as $login => $data) {
                    foreach (Board::cases() as $board) {
                        $rows[] = [
                            'login' => $login,
                            'board' => $board->value,
                            'window' => $month,
                            'score' => $data[$board->value.'_score'],
                            'breakdown' => json_encode($data['breakdown'][$board->value] ?? [], JSON_THROW_ON_ERROR),
                            'computed_at' => $now,
                        ];
                    }
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                LeaderboardEntry::upsert($chunk, ['login', 'board', 'window'], ['score', 'breakdown', 'computed_at']);
            }
        });
    }

    /**
     * Rank rows per board and window in a fixed number of queries (one ordered
     * read + one bulk upsert per board per window), rather than one UPDATE per
     * contributor. Uses a batch upsert instead of a ROW_NUMBER() window UPDATE
     * to stay portable across MySQL (production) and SQLite (tests).
     *
     * @param  list<string>  $monthWindows  monthly windows to rank alongside rolling12
     */
    private function assignRanks(array $monthWindows = []): void
    {
        foreach (array_merge(['rolling12'], $monthWindows) as $window) {
            foreach (Board::cases() as $board) {
                $orderedLogins = LeaderboardEntry::query()
                    ->where('board', $board->value)
                    ->where('window', $window)
                    ->orderByDesc('score')
                    ->orderBy('id')
                    ->pluck('login');

                $rank = 0;
                $rows = [];
                foreach ($orderedLogins as $login) {
                    $rows[] = [
                        'login' => $login,
                        'board' => $board->value,
                        'window' => $window,
                        'rank' => ++$rank,
                    ];
                }

                if ($rows !== []) {
                    LeaderboardEntry::upsert($rows, ['login', 'board', 'window'], ['rank']);
                }
            }
        }
    }

    /**
     * Roll the same scored events up to organizations, point-in-time, and write
     * org_leaderboard_entries. Unresolved contributors fall under "Independent /
     * Unknown".
     *
     * @param  list<\App\DataTransferObjects\Leaderboard\ScoredEvent>  $events
     */
    private function computeCompanyScores(array $events, Carbon $now): void
    {
        $companies = (new CompanyScoreAggregator(LeaderboardScorer::fromConfig()))
            ->aggregate($events, $now, MembershipResolver::fromDatabase());

        if ($companies === []) {
            // No companies this run — clear stale org snapshot rather than
            // leaving the previous run's entries in place. (An empty whereNotIn
            // below would delete nothing, so handle it explicitly here.)
            OrgLeaderboardEntry::query()->where('window', 'rolling12')->delete();

            return;
        }

        $independent = Organization::firstOrCreate(
            ['slug' => 'unknown'],
            ['name' => 'Unknown', 'type' => 'unknown'],
        );

        // Merge by final organization id (the unknown bucket maps to Independent).
        $byOrg = [];
        foreach ($companies as $company) {
            $organizationId = $company['organization_id'] ?? $independent->id;

            $byOrg[$organizationId] ??= ['contributor_score' => 0.0, 'maintainer_score' => 0.0, 'member_count' => 0];
            $byOrg[$organizationId]['contributor_score'] += $company['contributor_score'];
            $byOrg[$organizationId]['maintainer_score'] += $company['maintainer_score'];
            $byOrg[$organizationId]['member_count'] += $company['member_count'];
        }

        $orgRows = [];
        foreach ($byOrg as $organizationId => $scores) {
            foreach (Board::cases() as $board) {
                $orgRows[] = [
                    'organization_id' => $organizationId,
                    'board' => $board->value,
                    'window' => 'rolling12',
                    'score' => round($scores[$board->value.'_score'], 4),
                    'member_count' => $scores['member_count'],
                    'computed_at' => $now,
                ];
            }
        }

        OrgLeaderboardEntry::query()
            ->where('window', 'rolling12')
            ->whereNotIn('organization_id', array_keys($byOrg))
            ->delete();

        OrgLeaderboardEntry::upsert($orgRows, ['organization_id', 'board', 'window'], [
            'score', 'member_count', 'computed_at',
        ]);

        $this->assignOrgRanks();
    }

    private function assignOrgRanks(): void
    {
        foreach (Board::cases() as $board) {
            $orderedIds = OrgLeaderboardEntry::query()
                ->where('board', $board->value)
                ->where('window', 'rolling12')
                ->orderByDesc('score')
                ->orderBy('id')
                ->pluck('organization_id');

            $rank = 0;
            $rows = [];
            foreach ($orderedIds as $organizationId) {
                $rows[] = [
                    'organization_id' => $organizationId,
                    'board' => $board->value,
                    'window' => 'rolling12',
                    'rank' => ++$rank,
                ];
            }

            if ($rows !== []) {
                OrgLeaderboardEntry::upsert($rows, ['organization_id', 'board', 'window'], ['rank']);
            }
        }
    }
}
