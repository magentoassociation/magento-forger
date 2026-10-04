<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Leaderboard\Action;
use App\Helpers\GitHubLinkHelper;
use App\Models\GithubProfile;
use App\Models\GithubUserStat;
use App\Models\LeaderboardEntry;
use App\Models\LeaderboardLineItem;
use App\Models\OrgLeaderboardEntry;
use App\Models\RoleEligibility;
use App\Support\MonthlyWindow;
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
        return redirect()->route('leaderboard.show', ['board' => 'contributor']);
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

        // Full board (no cap): the #21 layer searches and jumps across the whole
        // population, so the client needs every ranked row, not the first 100.
        $entries = $board === 'maintainer'
            ? $this->maintainerRows((bool) auth()->user()?->canViewFullMaintainerBoard())
            : LeaderboardEntry::query()
                ->where('board', $board)
                ->where('window', 'rolling12')
                ->where('score', '>', 0)
                ->orderBy('rank')
                ->orderByDesc('score')
                ->get();

        return view('leaderboard.score', [
            'board' => $board,
            'boards' => self::BOARDS,
            'entries' => $entries,
            'profiles' => $this->profilesFor($entries->pluck('login')),
            'scoring' => $this->scoringExplainer($board),
            ...$this->boardChrome($entries, strtolower(self::BOARDS[$board]).'s', '12 months to '.Carbon::now()->format('M Y')),
        ]);
    }

    /**
     * Redirect to the current month's board so /leaderboard/monthly/{board} is a
     * stable entry point that always lands on the newest month.
     */
    public function monthlyIndex(string $board): RedirectResponse
    {
        if ($board !== 'contributor' && $board !== 'maintainer') {
            abort(404);
        }

        return redirect()->route('leaderboard.monthly', ['board' => $board, 'ym' => MonthlyWindow::allowed()[0]]);
    }

    /**
     * Per-calendar-month score board. Only the trailing months_back months are
     * viewable; anything older, in the future, or malformed 404s. Scores here
     * carry no recency decay (the month is the window), so the drill-down —
     * which reconciles against the decayed rolling total — is omitted.
     */
    public function monthly(string $board, string $ym): View
    {
        if ($board !== 'contributor' && $board !== 'maintainer') {
            abort(404);
        }

        $allowed = MonthlyWindow::allowed();

        if (! in_array($ym, $allowed, true)) {
            abort(404);
        }

        $entries = LeaderboardEntry::query()
            ->where('board', $board)
            ->where('window', $ym)
            ->where('score', '>', 0)
            ->orderBy('rank')
            ->orderByDesc('score')
            ->get();

        $months = array_map(fn (string $month): array => [
            'ym' => $month,
            'label' => MonthlyWindow::label($month),
            'active' => $month === $ym,
        ], $allowed);

        return view('leaderboard.score-monthly', [
            'board' => $board,
            'boards' => self::BOARDS,
            'ym' => $ym,
            'monthLabel' => MonthlyWindow::label($ym),
            'months' => $months,
            'entries' => $entries,
            'profiles' => $this->profilesFor($entries->pluck('login')),
            'scoring' => $this->scoringExplainer($board, decay: false),
            ...$this->boardChrome(
                $entries,
                strtolower(self::BOARDS[$board]).'s',
                Carbon::createFromFormat('!Y-m', $ym)->format('F Y'),
            ),
        ]);
    }

    /**
     * Per-user drill-down for a single month: the same page as the rolling
     * detail, scoped to the month's line items and summed on flat (no-decay)
     * points so the total reconciles with the monthly board. Same
     * in-range/real/not-future guards as the monthly board.
     */
    public function monthlyDetail(string $board, string $ym, string $login): View
    {
        if ($board !== 'contributor' && $board !== 'maintainer') {
            abort(404);
        }

        if (! in_array($ym, MonthlyWindow::allowed(), true)) {
            abort(404);
        }

        $items = LeaderboardLineItem::query()
            ->where('login', $login)
            ->where('board', $board)
            ->where('month', $ym)
            ->where('points_flat', '>', 0)
            ->orderByDesc('points_flat')
            ->get();

        return $this->detailPage($board, $login, $items, $ym);
    }

    /**
     * Shared chrome data for the #21 board layer: the population total, the
     * signed-in viewer's rank (or a not-ranked flag) and the caption noun/window.
     * The whole board is rendered; the page's inline script paginates it (?rows=)
     * so search and jump-to-rank reach the full population without extra requests.
     *
     * @param  Collection<int, object>  $entries  ranked rows, in display order
     * @return array{total: int, noun: string, windowCaption: string, viewerLogin: ?string, viewerRank: ?int, viewerNotRanked: bool}
     */
    private function boardChrome(Collection $entries, string $noun, string $windowCaption): array
    {
        $total = $entries->count();

        $viewerLogin = auth()->user()?->github_username;
        $viewerRank = null;

        if ($viewerLogin !== null) {
            foreach ($entries->values() as $i => $entry) {
                if ($entry->login === $viewerLogin) {
                    $viewerRank = (int) ($entry->rank ?? $i + 1);
                    break;
                }
            }
        }

        return [
            'total' => $total,
            'noun' => $noun,
            'windowCaption' => $windowCaption,
            'viewerLogin' => $viewerLogin,
            'viewerRank' => $viewerRank,
            'viewerNotRanked' => $viewerLogin !== null && $viewerRank === null,
        ];
    }

    /**
     * Data for the "How scoring works" modal: the configured weights for
     * this board plus the multipliers, so the modal stays in sync with config.
     * $decay is false for the monthly boards, which apply impact but no recency
     * decay, so the modal can drop the recency copy.
     *
     * @return array{
     *     weights: array<string, int|float>,
     *     impact: array<string, int|float>,
     *     recency: array<string, int>,
     *     labels: array<string, string>,
     *     impactActions: list<string>,
     *     scoredList: string,
     *     decay: bool,
     *     impactExamples: list<array{label: string, factor: float}>,
     *     recencyExamples: list<array{label: string, factor: float}>
     * }
     */
    private function scoringExplainer(string $board, bool $decay = true): array
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

        $impact = (array) config('leaderboard.impact', ['min' => 1, 'max' => 5]);
        $recency = (array) config('leaderboard.recency', ['window_days' => 365, 'half_life_days' => 182]);

        return [
            'weights' => $weights,
            'impact' => $impact,
            'recency' => $recency,
            'labels' => collect(Action::cases())
                ->mapWithKeys(fn (Action $action): array => [$action->value => $action->label()])
                ->all(),
            'impactActions' => ['issue_opened', 'pr_opened', 'pr_merged', 'issue_resolved_by_merge', 'approved_then_merged'],
            'scoredList' => $this->humanJoin($scored),
            'decay' => $decay,
            'impactExamples' => $this->impactExamples($impact),
            'recencyExamples' => $decay ? $this->recencyExamples($recency) : [],
        ];
    }

    /**
     * "Priority label → multiplier" rows for the impact explainer, read straight
     * from config so the modal can never drift from the configured priorities.
     * The trailing row shows the unlabeled default.
     *
     * @param  array{priority?: array<string, int|float>}  $impact
     * @return list<array{label: string, factor: float}>
     */
    private function impactExamples(array $impact): array
    {
        $rows = [];

        foreach ((array) ($impact['priority'] ?? []) as $label => $multiplier) {
            $rows[] = ['label' => $label, 'factor' => (float) $multiplier];
        }

        $rows[] = ['label' => 'No priority label', 'factor' => 1.0];

        return $rows;
    }

    /**
     * Worked "age → multiplier" rows for the recency-decay explainer, derived from
     * the configured half-life and window so they always match the scorer.
     *
     * @param  array{window_days: int, half_life_days: int}  $recency
     * @return list<array{label: string, factor: float}>
     */
    private function recencyExamples(array $recency): array
    {
        $halfLife = (int) $recency['half_life_days'];
        $window = (int) $recency['window_days'];

        return [
            ['label' => 'today', 'factor' => 1.0],
            ['label' => $halfLife.' days ago', 'factor' => 0.5],
            ['label' => 2 * $halfLife.' days ago', 'factor' => round(2 ** (-2), 2)],
            ['label' => 'more than '.$window.' days ago', 'factor' => 0.0],
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

    /**
     * Plural group headings for the detail page, keyed by action. Falls back to
     * the singular Action label for anything not listed.
     *
     * @var array<string, string>
     */
    private const GROUP_LABELS = [
        'pr_opened' => 'PRs opened',
        'pr_merged' => 'PRs merged',
        'issue_opened' => 'Issues opened',
        'issue_resolved_by_merge' => 'Issues resolved by a merged PR',
        'review_approved' => 'PRs approved',
        'review_rejected' => 'Changes requested',
        'review_commented' => 'Review comments',
        'approved_then_merged' => 'Approved PRs that were merged',
        'pr_claimed' => 'Stale PRs claimed',
        'label_applied' => 'Triage labels applied',
    ];

    /**
     * List-view type tags: the singular of each group name — a row is one item.
     *
     * @var array<string, string>
     */
    private const CHIP_LABELS = [
        'pr_opened' => 'PR opened',
        'pr_merged' => 'PR merged',
        'issue_opened' => 'Issue opened',
        'issue_resolved_by_merge' => 'Issue resolved by a merged PR',
        'review_approved' => 'PR approved',
        'review_rejected' => 'Change requested',
        'review_commented' => 'Review comment',
        'approved_then_merged' => 'Approved PR that was merged',
        'pr_claimed' => 'Stale PR claimed',
        'label_applied' => 'Triage label applied',
    ];

    /** Rows per group shown before the "Show all" control on the grouped view. */
    private const DETAIL_GROUP_PREVIEW = 5;

    public function detail(string $board, string $login): View
    {
        if ($board !== 'contributor' && $board !== 'maintainer') {
            abort(404);
        }

        // Read the line items persisted by leaderboard:compute — the exact events
        // that produced the board score, with their points — so the drill-down
        // total reconciles with the board instead of re-deriving a different set.
        $items = LeaderboardLineItem::query()
            ->where('login', $login)
            ->where('board', $board)
            ->orderByDesc('points')
            ->get();

        return $this->detailPage($board, $login, $items);
    }

    /**
     * The one detail page behind both the rolling (12-month, decayed) and the
     * monthly (one month, flat points) drill-downs. $ym is null for rolling;
     * for monthly the items are already scoped to that month and the month
     * chips navigate between monthly pages instead of filtering.
     *
     * @param  Collection<int, LeaderboardLineItem>  $items
     */
    private function detailPage(string $board, string $login, Collection $items, ?string $ym = null): View
    {
        $flat = $ym !== null;
        $points = fn (LeaderboardLineItem $item): float => (float) ($flat ? $item->points_flat : $item->points);

        // Points descending, ties newest first. Every sort below is stable, so
        // this order is the tie-break for groups and all three list sorts.
        $items = $items->sortBy([
            fn (LeaderboardLineItem $a, LeaderboardLineItem $b): int => $points($b) <=> $points($a),
            fn (LeaderboardLineItem $a, LeaderboardLineItem $b): int => ($b->contributed_at?->timestamp ?? 0) <=> ($a->contributed_at?->timestamp ?? 0),
        ])->values();

        // Base weights power the per-row "base × priority × recency" hint.
        $weights = (array) config('leaderboard.weights.'.$board, []);

        // Month chips: a fixed run of months, most recent first. Rolling pages
        // filter by ?month= over the last 12; monthly pages link between months.
        $months = collect($flat ? MonthlyWindow::allowed() : MonthlyWindow::allowed(12));
        $activeMonth = $flat ? $ym : ($months->contains(request('month')) ? request('month') : null);
        $scoped = $flat || $activeMonth === null
            ? $items
            : $items->filter(fn (LeaderboardLineItem $item): bool => $item->contributed_at?->format('Y-m') === $activeMonth);

        $group = fn (Collection $rows): Collection => $rows
            ->groupBy('action')
            ->map(fn (Collection $rows, string $action): object => (object) [
                'key' => $action,
                'name' => self::GROUP_LABELS[$action] ?? Action::labelFor($action),
                'count' => $rows->count(),
                'total' => round($rows->sum($points), 1),
                'rows' => $rows->map(fn (LeaderboardLineItem $item): object => $this->detailRow($item, $weights, $flat))->values(),
            ])
            ->sortByDesc('total')
            ->values();

        // Grouped view (#9b): grouped by action, subtotals descending, so the
        // subtotals add up to the headline score.
        $groups = $group($scoped);

        // Flat view (#9a): one sortable list, action shown as a tag.
        $sort = in_array(request('sort'), ['date', 'type'], true) ? request('sort') : 'points';
        $list = $scoped->map(fn (LeaderboardLineItem $item): object => $this->detailRow($item, $weights, $flat));
        // Type follows the group tiles' order (subtotal descending); within a
        // type the incoming points order stands.
        $typeOrder = $groups->pluck('key')->flip();
        $list = match ($sort) {
            'date' => $list->sortByDesc(fn (object $row): int => $row->date?->timestamp ?? 0),
            'type' => $list->sortBy(fn (object $row): int => $typeOrder[$row->tagKey]),
            default => $list,
        };

        // Cross-link: the paired page exists when the person also scores on the
        // other board. Presence, not permission, drives it (see README-detail-page).
        $otherBoard = $board === 'contributor' ? 'maintainer' : 'contributor';
        $onOtherBoard = LeaderboardEntry::query()
            ->where('board', $otherBoard)
            ->where('window', 'rolling12')
            ->where('login', $login)
            ->where('score', '>', 0)
            ->exists();

        return view('leaderboard.score-detail', [
            'board' => $board,
            'boards' => self::BOARDS,
            'login' => $login,
            'ym' => $ym,
            'profile' => GithubProfile::query()->where('login', $login)->first(),
            'scoring' => $this->scoringExplainer($board, decay: ! $flat),
            'otherBoard' => $otherBoard,
            'otherBoardName' => self::BOARDS[$otherBoard],
            'onOtherBoard' => $onOtherBoard,
            // Zero-state panel: the board's scoring groups in config order, so it is
            // the scoring rules with zeros in them and never a hand-kept list.
            'scoringGroups' => array_map(
                fn (string $action): string => self::GROUP_LABELS[$action] ?? Action::labelFor($action),
                array_keys($weights),
            ),
            'cta' => $this->emptyStateCta($board),
            'zero' => $items->isEmpty(),
            'view' => request('view') === 'list' ? 'list' : 'grouped',
            'groups' => $groups,
            'activeGroup' => $groups->firstWhere('key', request('group')),
            'preview' => self::DETAIL_GROUP_PREVIEW,
            'flat' => $list->values(),
            'sort' => $sort,
            'maxTotal' => (float) ($groups->max('total') ?: 1),
            'months' => $months,
            'activeMonth' => $activeMonth,
            'scoreLabel' => $flat ? MonthlyWindow::label($ym) : '12 months',
            // The headline is the whole window's score — a month chip filters the
            // groups below it, not the score. Summed from the displayed subtotals'
            // rounding so the unfiltered page reconciles with itself.
            'total' => round($group($items)->sum('total'), 1),
        ]);
    }

    /**
     * A single detail-page row derived from a line item, with the action tag the
     * list view shows. $flat reads the no-decay points (monthly pages).
     *
     * @param  array<string, int|float>  $weights  action => base weight
     */
    private function detailRow(LeaderboardLineItem $item, array $weights = [], bool $flat = false): object
    {
        return (object) [
            'title' => $item->title ?: $item->url,
            'url' => $item->url,
            'date' => $item->contributed_at,
            'points' => round($flat ? $item->points_flat : $item->points, 2),
            'formula' => $this->scoreFormula($item, $weights, $flat),
            'tag' => self::CHIP_LABELS[$item->action] ?? Action::labelFor($item->action),
            'tagKey' => $item->action,
        ];
    }

    /**
     * The zero-score empty state's call to action, per board. Contributor points at
     * the same "Ready for Work" issue filter as the homepage CTA; maintainer at the
     * open PRs awaiting review.
     *
     * @return array{label: string, url: string}
     */
    private function emptyStateCta(string $board): array
    {
        if ($board === 'maintainer') {
            return [
                'label' => 'Find a PR to review →',
                'url' => GitHubLinkHelper::pullRequestLabelUrl((string) config('leaderboard.pending_review_label')),
            ];
        }

        return [
            'label' => 'Find an issue to work on →',
            'url' => GitHubLinkHelper::issueLabelUrl((string) config('homepage.paths.0.label')),
        ];
    }

    /**
     * Human-readable "base × priority × recency" decomposition of a line item's
     * decayed points, derived from the stored decayed/flat points and the
     * configured base weight. Returns null when it can't be decomposed (unknown
     * weight or zero flat points) so the row falls back to the bare total.
     *
     * @param  array<string, int|float>  $weights  action => base weight
     */
    private function scoreFormula(LeaderboardLineItem $item, array $weights, bool $flat = false): ?string
    {
        $base = (float) ($weights[$item->action] ?? 0);

        if ($base <= 0.0 || $item->points_flat <= 0.0) {
            return null;
        }

        // points_flat = base × priority; points = points_flat × recency. The
        // monthly boards apply no recency decay, so $flat drops that term and
        // totals to the flat points.
        $priorityFactor = $item->points_flat / $base;
        $total = $flat ? $item->points_flat : $item->points;

        $parts = [$this->trimNumber($base).' base'];

        if (abs($priorityFactor - 1.0) >= 0.05) {
            // issue_opened folds in the confirmed-label bonus, so it's not purely priority.
            $factorLabel = $item->action === Action::ISSUE_OPENED->value ? 'impact' : 'priority';
            $parts[] = '× '.$this->trimNumber(round($priorityFactor, 1)).'× '.$factorLabel;
        }

        if (! $flat) {
            $recency = $item->points / $item->points_flat;

            if (abs($recency - 1.0) >= 0.05) {
                $parts[] = '× '.$this->trimNumber(round($recency, 2)).'× recency';
            }
        }

        return implode(' ', $parts).' = '.$this->trimNumber(round($total, 1)).' pts';
    }

    /**
     * Format a factor for display: two decimals, trailing zeros and dot stripped
     * (6.00 → "6", 1.20 → "1.2", 0.55 → "0.55").
     */
    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

    /**
     * Standalone "How scoring works" page: the same explainer the leaderboard modal
     * shows, for every scored board, using the rolling (decayed) scoring.
     */
    public function scoring(): View
    {
        $scored = ['contributor', 'maintainer'];

        return view('leaderboard.scoring', [
            'boards' => self::BOARDS,
            'scorings' => collect($scored)
                ->mapWithKeys(fn (string $board): array => [$board => $this->scoringExplainer($board)])
                ->all(),
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

        // Comebacks default to most recently back first (who to welcome back);
        // the ?comebacks_sort=away toggle re-sorts by time away, longest first. No
        // cap here — the view shows 12 and the ?comebacks=all URL reveals the rest,
        // so the full count is needed to drive the "Show all N" affordance.
        $comebacksSort = request('comebacks_sort') === 'away' ? 'away' : 'recent';
        $comebacks = GithubUserStat::query()
            ->whereNotNull('returned_after_days')
            ->when(
                $comebacksSort === 'away',
                fn ($query) => $query->orderByDesc('returned_after_days'),
                fn ($query) => $query->orderByDesc('last_contributor_at'),
            )
            ->get();

        $recentlyActive = GithubUserStat::query()
            ->where('last_contributor_at', '>=', Carbon::now()->subDays(30))
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
            'comebacksSort' => $comebacksSort,
            'recentlyActive' => $recentlyActive,
            'profiles' => $this->profilesFor($logins),
        ]);
    }

    /**
     * The maintainer board shows the full Community Council roster — every
     * maintainer, even with a zero score — and excludes non-roster reviewers.
     * Falls back to "everyone with a maintainer score" when no roster is set.
     *
     * $includeZeros keeps idle (zero-score) maintainers on the list; it's true
     * for admins, maintainers, and community council members and false for the
     * public, who only see maintainers who are actually scoring.
     *
     * @return Collection<int, object>
     */
    private function maintainerRows(bool $includeZeros): Collection
    {
        $roster = RoleEligibility::query()->where('role', 'maintainer')->get(['login', 'active']);

        if ($roster->isEmpty()) {
            return LeaderboardEntry::query()
                ->where('board', 'maintainer')
                ->where('window', 'rolling12')
                ->where('score', '>', 0)
                ->orderBy('rank')
                ->orderByDesc('score')
                ->get();
        }

        $scores = LeaderboardEntry::query()
            ->where('board', 'maintainer')
            ->where('window', 'rolling12')
            ->whereIn('login', $roster->pluck('login'))
            ->get()
            ->keyBy('login');

        return $roster
            ->map(fn (RoleEligibility $member): object => (object) [
                'login' => $member->login,
                'active' => (bool) $member->active,
                'score' => (float) (optional($scores->get($member->login))->score ?? 0.0),
                'breakdown' => optional($scores->get($member->login))->breakdown ?? [],
            ])
            ->when(! $includeZeros, fn (Collection $rows): Collection => $rows->filter(
                fn (object $row): bool => $row->score > 0,
            ))
            ->sortBy([['score', 'desc'], ['active', 'desc']])
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
                'organization' => optional($group->first()->organization)->name ?? 'Unclaimed',
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
