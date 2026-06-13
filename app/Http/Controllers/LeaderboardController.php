<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Dashboard\ContributorCount;
use App\Models\User;
use App\Queries\Dashboard\IssuesClosedLeaderboardQuery;
use App\Queries\Dashboard\IssuesOpenedLeaderboardQuery;
use App\Queries\Dashboard\PRsClosedLeaderboardQuery;
use App\Queries\Dashboard\PRsMergedLeaderboardQuery;
use App\Queries\Dashboard\PRsOpenedLeaderboardQuery;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    private const METRICS = [
        'prs-merged' => ['label' => 'PRs Merged',      'query' => PRsMergedLeaderboardQuery::class],
        'prs-opened' => ['label' => 'PRs Opened',      'query' => PRsOpenedLeaderboardQuery::class],
        'prs-closed' => ['label' => 'PRs Closed',      'query' => PRsClosedLeaderboardQuery::class],
        'issues-opened' => ['label' => 'Issues Opened',   'query' => IssuesOpenedLeaderboardQuery::class],
        'issues-closed' => ['label' => 'Issues Closed',   'query' => IssuesClosedLeaderboardQuery::class],
    ];

    public function index(): RedirectResponse
    {
        return redirect()->route('leaderboard.show', ['metric' => 'prs-merged']);
    }

    public function show(Request $request, string $metric): View
    {
        if (! isset(self::METRICS[$metric])) {
            abort(404);
        }

        [$from, $to, $period] = $this->resolvePeriod($request);

        $queryClass = self::METRICS[$metric]['query'];
        $query = app($queryClass);

        $contributors = [];
        $dataMissing = false;

        try {
            $raw = $query->execute($from, $to);
            $contributors = $this->enrichWithCompany($raw);
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                throw $e;
            }
            $dataMissing = true;
        }

        return view('leaderboard.contributor', [
            'metric' => $metric,
            'metricLabel' => self::METRICS[$metric]['label'],
            'metrics' => self::METRICS,
            'contributors' => $contributors,
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'dataMissing' => $dataMissing,
        ]);
    }

    /**
     * @param  list<ContributorCount>  $contributors
     * @return list<ContributorCount>
     */
    private function enrichWithCompany(array $contributors): array
    {
        if (empty($contributors)) {
            return [];
        }

        $logins = array_map(fn ($c) => $c->login, $contributors);

        $users = User::with(['affiliations' => fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()), 'affiliations.company'])
            ->whereIn('github_username', $logins)
            ->get()
            ->keyBy('github_username');

        return array_map(function (ContributorCount $contributor) use ($users) {
            $user = $users->get($contributor->login);
            $company = $user?->affiliations->first()?->company?->name;

            return new ContributorCount(
                login: $contributor->login,
                count: $contributor->count,
                company: $company,
            );
        }, $contributors);
    }

    /**
     * @return array{Carbon, Carbon, string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = $request->get('period', 'last-month');

        return match ($period) {
            'last-quarter' => $this->lastQuarter(),
            'last-year' => [Carbon::now()->subYear()->startOfYear(), Carbon::now()->subYear()->endOfYear(), $period],
            'custom' => [Carbon::parse($request->get('from')), Carbon::parse($request->get('to')), $period],
            default => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth(), 'last-month'],
        };
    }

    /**
     * @return array{Carbon, Carbon, string}
     */
    private function lastQuarter(): array
    {
        $now = Carbon::now();
        $currentQuarter = (int) ceil($now->month / 3);
        $lastQuarter = $currentQuarter - 1;

        if ($lastQuarter === 0) {
            $lastQuarter = 4;
            $year = $now->year - 1;
        } else {
            $year = $now->year;
        }

        $from = Carbon::create($year, ($lastQuarter - 1) * 3 + 1, 1)->startOfDay();
        $to = $from->copy()->addMonths(3)->subDay()->endOfDay();

        return [$from, $to, 'last-quarter'];
    }
}
