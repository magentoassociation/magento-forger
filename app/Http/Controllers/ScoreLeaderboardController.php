<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ContributionItem;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Models\GithubProfile;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Models\OrgLeaderboardEntry;
use App\Models\RoleEligibility;
use App\Services\Leaderboard\ContributionDetailReader;
use App\Services\Leaderboard\LeaderboardScorer;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ScoreLeaderboardController extends Controller
{
    private const BOARDS = [
        'contributor' => 'Contributor',
        'maintainer' => 'Maintainer',
        'company' => 'Company',
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('scores.show', ['board' => 'contributor']);
    }

    public function show(string $board): View
    {
        if (! isset(self::BOARDS[$board])) {
            abort(404);
        }

        if ($board === 'company') {
            return view('leaderboard.score-company', [
                'board' => $board,
                'boards' => self::BOARDS,
                'entries' => $this->companyRows(),
            ]);
        }

        $entries = $board === 'maintainer'
            ? $this->maintainerRows()
            : LeaderboardEntry::query()
                ->where('board', $board)
                ->where('window', 'rolling12')
                ->where('score', '>', 0)
                ->orderBy('rank')
                ->orderByDesc('score')
                ->limit(100)
                ->get();

        return view('leaderboard.score', [
            'board' => $board,
            'boards' => self::BOARDS,
            'entries' => $entries,
            'profiles' => $this->profilesFor($entries->pluck('login')),
            'scoring' => $this->scoringExplainer($board),
        ]);
    }

    /**
     * Data for the "How are scores tallied?" modal: the configured weights for
     * this board plus the multipliers, so the modal stays in sync with config.
     *
     * @return array{
     *     weights: array<string, int|float>,
     *     impact: array<string, int|float>,
     *     recency: array<string, int>,
     *     labels: array<string, string>,
     *     impactActions: list<string>,
     *     scoredList: string
     * }
     */
    private function scoringExplainer(string $board): array
    {
        $weights = (array) config('leaderboard.weights.'.$board, []);

        // Short, lowercase gerund phrases for the inline "what gets scored" list,
        // keyed the same as the weights so only configured actions are listed.
        $phrases = [
            'issue_opened' => 'opening issues',
            'pr_opened' => 'opening PRs',
            'pr_merged' => 'getting a PR merged',
            'issue_resolved_by_merge' => 'closing an issue with a merged PR',
            'review_approved' => 'approving PRs',
            'review_rejected' => 'requesting changes on PRs',
            'review_commented' => 'commenting on reviews',
            'approved_then_merged' => 'approving PRs that later merge',
            'pr_claimed' => 'picking up long-pending PRs',
            'label_applied' => 'applying triage labels',
        ];

        $scored = array_map(
            fn (string $action): string => $phrases[$action] ?? str_replace('_', ' ', $action),
            array_keys($weights),
        );

        return [
            'weights' => $weights,
            'impact' => (array) config('leaderboard.impact', ['min' => 1, 'max' => 5]),
            'recency' => (array) config('leaderboard.recency', ['window_days' => 365, 'half_life_days' => 182]),
            'labels' => collect(Action::cases())
                ->mapWithKeys(fn (Action $action): array => [$action->value => $action->label()])
                ->all(),
            'impactActions' => ['pr_merged', 'issue_resolved_by_merge', 'approved_then_merged'],
            'scoredList' => $this->humanJoin($scored),
        ];
    }

    /**
     * Join phrases into a readable list: "a, b and c".
     *
     * @param  list<string>  $items
     */
    private function humanJoin(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);
        $separator = count($items) > 1 ? ', and ' : ' and ';

        return implode(', ', $items).$separator.$last;
    }

    public function detail(string $board, string $login, ContributionDetailReader $reader): View
    {
        if ($board !== 'contributor' && $board !== 'maintainer') {
            abort(404);
        }

        $boardEnum = $board === 'contributor' ? Board::CONTRIBUTOR : Board::MAINTAINER;

        // Anchor recency decay to when the leaderboard was last computed, not the
        // current request time. Using a fresh now() would let the decay factor
        // drift past the persisted entry, so the detail total would no longer
        // reconcile with the score shown on the board. Falls back to now() only
        // when no entry has been persisted yet.
        $entry = LeaderboardEntry::query()
            ->where('login', $login)
            ->where('board', $board)
            ->where('window', 'rolling12')
            ->first();
        $asOf = $entry?->computed_at ?? Carbon::now();
        $from = $asOf->copy()->subDays((int) config('leaderboard.recency.window_days', 365));
        $scorer = LeaderboardScorer::fromConfig();

        $rows = collect($reader->readForLogin($login, $from, $asOf))
            ->filter(fn (ContributionItem $item): bool => $item->board === $boardEnum)
            ->map(fn (ContributionItem $item): object => (object) [
                'action' => $item->action->label(),
                'title' => $item->title,
                'url' => $item->url,
                'date' => $item->date,
                'points' => round($scorer->points(
                    new ScoredEvent($login, $item->board, $item->action, $item->date, $item->impact),
                    $asOf,
                ), 2),
            ])
            ->sortByDesc('points')
            ->values();

        return view('leaderboard.score-detail', [
            'board' => $board,
            'boards' => self::BOARDS,
            'login' => $login,
            'profile' => GithubProfile::query()->where('login', $login)->first(),
            'rows' => $rows,
            'total' => round($rows->sum('points'), 1),
        ]);
    }

    public function highlights(): View
    {
        $newContributorCutoff = Carbon::now()->subDays((int) config('leaderboard.spotlight.window_days', 30));

        $newContributors = GithubUserStat::query()
            ->whereNotNull('first_contribution_at')
            ->where('first_contribution_at', '>=', $newContributorCutoff)
            ->orderByDesc('contributor_score')
            ->limit(20)
            ->get();

        $rising = GithubUserStat::query()
            ->whereColumn('contributor_score', '>', 'rising_baseline_score')
            ->orderByRaw('(contributor_score - rising_baseline_score) desc')
            ->limit(20)
            ->get();

        $comebacks = GithubUserStat::query()
            ->whereNotNull('returned_after_days')
            ->orderByDesc('returned_after_days')
            ->limit(20)
            ->get();

        $recentlyActive = GithubUserStat::query()
            ->where('last_contributor_at', '>=', Carbon::now()->subDays(14))
            ->where('contributor_score', '>', 0)
            ->orderByDesc('contributor_score')
            ->limit(20)
            ->get();

        $logins = $newContributors->pluck('login')
            ->merge($rising->pluck('login'))
            ->merge($comebacks->pluck('login'))
            ->merge($recentlyActive->pluck('login'));

        return view('leaderboard.score-highlights', [
            'board' => 'highlights',
            'boards' => self::BOARDS,
            'newContributors' => $newContributors,
            'rising' => $rising,
            'comebacks' => $comebacks,
            'recentlyActive' => $recentlyActive,
            'profiles' => $this->profilesFor($logins),
        ]);
    }

    /**
     * The maintainer board shows the full Community Council roster — every
     * maintainer, even with a zero score — and excludes non-roster reviewers.
     * Falls back to "everyone with a maintainer score" when no roster is set.
     *
     * @return Collection<int, object>
     */
    private function maintainerRows(): Collection
    {
        $roster = RoleEligibility::query()->where('role', 'maintainer')->pluck('login');

        if ($roster->isEmpty()) {
            return LeaderboardEntry::query()
                ->where('board', 'maintainer')
                ->where('window', 'rolling12')
                ->where('score', '>', 0)
                ->orderBy('rank')
                ->orderByDesc('score')
                ->limit(100)
                ->get();
        }

        $scores = LeaderboardEntry::query()
            ->where('board', 'maintainer')
            ->where('window', 'rolling12')
            ->whereIn('login', $roster)
            ->get()
            ->keyBy('login');

        return $roster
            ->map(fn (string $login): object => (object) [
                'login' => $login,
                'score' => (float) (optional($scores->get($login))->score ?? 0.0),
                'breakdown' => optional($scores->get($login))->breakdown ?? [],
            ])
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Merge per-board org rows into one row per organization for display.
     *
     * @return Collection<int, object>
     */
    private function companyRows(): Collection
    {
        return OrgLeaderboardEntry::query()
            ->where('window', 'rolling12')
            ->with('organization')
            ->get()
            ->groupBy('organization_id')
            ->map(fn (Collection $group): object => (object) [
                'organization' => optional($group->first()->organization)->name ?? 'Unknown',
                'contributor_score' => (float) (optional($group->firstWhere('board', 'contributor'))->score ?? 0),
                'maintainer_score' => (float) (optional($group->firstWhere('board', 'maintainer'))->score ?? 0),
                'member_count' => (int) $group->max('member_count'),
            ])
            ->sortByDesc('contributor_score')
            ->values();
    }

    /**
     * Display profiles (name + avatar) keyed by login.
     *
     * @param  Collection<int, string>  $logins
     * @return Collection<string, GithubProfile>
     */
    private function profilesFor(Collection $logins): Collection
    {
        return GithubProfile::query()
            ->whereIn('login', $logins->all())
            ->get()
            ->keyBy('login');
    }
}
