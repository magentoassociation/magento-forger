<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\GitHubLinkHelper;
use App\Models\GithubProfile;
use App\Models\LeaderboardEntry;
use App\Services\HomepageCountsService;
use App\Services\Search\OpenSearchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function index(OpenSearchService $search, HomepageCountsService $counts): View
    {
        $dataMissing = false;
        $prStats = $this->openedClosedPerMonth($search->searchPRs(...), 'prs', $dataMissing);
        $issueStats = $this->openedClosedPerMonth($search->searchIssues(...), 'issues', $dataMissing);

        $labelCounts = $counts->labelCounts();

        $topFive = $this->topContributors();
        $viewerEntry = $this->viewerEntry();

        return view('welcome', [
            'prStats' => $prStats,
            'issueStats' => $issueStats,
            'dataMissing' => $dataMissing,
            'paths' => $this->buildPaths($labelCounts),
            'areas' => $this->buildAreas($labelCounts),
            'links' => config('homepage.links'),
            'topFive' => $topFive,
            'viewerEntry' => $viewerEntry,
            'profiles' => $this->profilesFor(
                $topFive->pluck('login')->push($viewerEntry?->login)->filter()
            ),
        ]);
    }

    /**
     * Top five of the rolling-12-month Contributor board — a truncation of the
     * leaderboard table, same ranking and scores.
     *
     * @return Collection<int, LeaderboardEntry>
     */
    private function topContributors(): Collection
    {
        return LeaderboardEntry::query()
            ->where('board', 'contributor')
            ->where('window', 'rolling12')
            ->where('score', '>', 0)
            ->orderBy('rank')
            ->orderByDesc('score')
            ->limit(5)
            ->get();
    }

    /**
     * The signed-in visitor's own Contributor board row, if any. Null when signed
     * out or when the account has never scored in the window.
     */
    private function viewerEntry(): ?LeaderboardEntry
    {
        $login = auth()->user()?->github_username;

        if (! $login) {
            return null;
        }

        return LeaderboardEntry::query()
            ->where('board', 'contributor')
            ->where('window', 'rolling12')
            ->where('login', $login)
            ->first();
    }

    /**
     * GitHub display profiles keyed by login, for avatars and names.
     *
     * @param  SupportCollection<int, string>  $logins
     * @return SupportCollection<string, GithubProfile>
     */
    private function profilesFor(SupportCollection $logins): SupportCollection
    {
        return GithubProfile::query()
            ->whereIn('login', $logins->unique()->values()->all())
            ->get()
            ->keyBy('login');
    }

    /**
     * Resolve the §3 "Choose how you want to help" path cards with live counts and links.
     *
     * @param  array<string, int>  $labelCounts
     * @return list<array{icon: string, title: string, blurb: string, cta: string, count: ?int, url: string}>
     */
    private function buildPaths(array $labelCounts): array
    {
        return array_map(static function (array $path) use ($labelCounts): array {
            $unclaimed = (bool) ($path['unclaimed_only'] ?? false);

            return [
                'icon' => $path['icon'],
                'title' => $path['title'],
                'blurb' => $path['blurb'],
                'cta' => $path['cta'],
                // The index has no assignee/linked-PR data, so an unclaimed path can't be
                // counted locally; it renders without a pill rather than show a wrong number.
                'count' => $unclaimed ? null : $labelCounts[$path['label']] ?? null,
                'url' => GitHubLinkHelper::issueLabelUrl($path['label'], $unclaimed),
            ];
        }, config('homepage.paths'));
    }

    /**
     * Resolve the §4 "Pick your area" tiles. Labels resolving to zero open issues are
     * dropped so a renamed or emptied area degrades gracefully.
     *
     * @param  array<string, int>  $labelCounts
     * @return list<array{name: string, count: int, url: string}>
     */
    private function buildAreas(array $labelCounts): array
    {
        $areas = [];
        foreach (config('homepage.areas') as $label) {
            // A terms aggregation omits labels with no matching docs, so an absent bucket
            // means zero open issues — treat it the same as zero and drop the tile.
            $count = $labelCounts[$label] ?? 0;
            if ($count === 0) {
                continue;
            }
            $areas[] = [
                'name' => str_replace('Area: ', '', $label),
                'count' => $count,
                'url' => GitHubLinkHelper::issueLabelUrl($label),
            ];
        }

        // Busiest areas first (README "Pick your area": by open count, descending).
        usort($areas, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $areas;
    }
}
