<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\Dashboard\IssuesClosedLeaderboardQuery;
use App\Queries\Dashboard\IssuesOpenedLeaderboardQuery;
use App\Queries\Dashboard\PRsMergedLeaderboardQuery;
use App\Queries\Dashboard\PRsOpenedLeaderboardQuery;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class LeaderboardController extends Controller
{
    private const METRICS = [
        'prs-merged' => ['label' => 'PRs Merged',      'query' => PRsMergedLeaderboardQuery::class],
        'prs-opened' => ['label' => 'PRs Opened',      'query' => PRsOpenedLeaderboardQuery::class],
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
            $contributors = $query->execute($from, $to);
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
            'githubUrl' => $this->buildGitHubUrlResolver($metric, $from->toDateString(), $to->toDateString()),
        ]);
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
            'custom' => $this->resolveCustomPeriod($request),
            default => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth(), 'last-month'],
        };
    }

    /**
     * @return array{Carbon, Carbon, string}
     */
    private function resolveCustomPeriod(Request $request): array
    {
        $lastMonth = [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth(), 'last-month'];

        try {
            $from = Carbon::parse($request->get('from'));
            $to = Carbon::parse($request->get('to'));
        } catch (Throwable) {
            return $lastMonth;
        }

        return [$from, $to, 'custom'];
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

    private function buildGitHubUrlResolver(string $metric, string $from, string $to): \Closure
    {
        $repo = config('github.repo');
        $base = "https://github.com/{$repo}";
        $range = "{$from}..{$to}";

        return match ($metric) {
            'prs-merged' => fn (string $login) => "{$base}/pulls?q=is:pr+is:merged+author:{$login}+merged:{$range}",
            'prs-opened' => fn (string $login) => "{$base}/pulls?q=is:pr+author:{$login}+created:{$range}",
            'issues-opened' => fn (string $login) => "{$base}/issues?q=is:issue+author:{$login}+created:{$range}",
            'issues-closed' => fn (string $login) => "{$base}/issues?q=is:issue+is:closed+reason:completed+author:{$login}+closed:{$range}",
            default => fn (string $login) => "https://github.com/{$login}",
        };
    }
}
