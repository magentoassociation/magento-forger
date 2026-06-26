<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ContributionItem;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
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
        ]);
    }

    public function detail(string $board, string $login, ContributionDetailReader $reader): View
    {
        if ($board !== 'contributor' && $board !== 'maintainer') {
            abort(404);
        }

        $boardEnum = $board === 'contributor' ? Board::CONTRIBUTOR : Board::MAINTAINER;
        $now = Carbon::now();
        $from = $now->copy()->subDays((int) config('leaderboard.recency.window_days', 365));
        $scorer = LeaderboardScorer::fromConfig();

        $rows = collect($reader->readForLogin($login, $from, $now))
            ->filter(fn (ContributionItem $item): bool => $item->board === $boardEnum)
            ->map(fn (ContributionItem $item): object => (object) [
                'action' => $item->action->value,
                'title' => $item->title,
                'url' => $item->url,
                'date' => $item->date,
                'points' => round($scorer->points(new ScoredEvent($login, $item->board, $item->action, $item->date, $item->impact), $now), 2),
            ])
            ->sortByDesc('points')
            ->values();

        return view('leaderboard.score-detail', [
            'board' => $board,
            'boards' => self::BOARDS,
            'login' => $login,
            'rows' => $rows,
            'total' => round($rows->sum('points'), 1),
        ]);
    }

    public function highlights(): View
    {
        $newContributorCutoff = Carbon::now()->subDays(30);

        return view('leaderboard.score-highlights', [
            'board' => 'highlights',
            'boards' => self::BOARDS,
            'newContributors' => GithubUserStat::query()
                ->whereNotNull('first_contribution_at')
                ->where('first_contribution_at', '>=', $newContributorCutoff)
                ->orderByDesc('contributor_score')
                ->limit(20)
                ->get(),
            'rising' => GithubUserStat::query()
                ->whereColumn('contributor_score', '>', 'contributor_score_prev')
                ->orderByRaw('(contributor_score - contributor_score_prev) desc')
                ->limit(20)
                ->get(),
            'comebacks' => GithubUserStat::query()
                ->whereNotNull('returned_after_days')
                ->orderByDesc('returned_after_days')
                ->limit(20)
                ->get(),
            'recentlyActive' => GithubUserStat::query()
                ->where('current_gap_days', '<=', 14)
                ->where('contributor_score', '>', 0)
                ->orderByDesc('contributor_score')
                ->limit(20)
                ->get(),
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
}
