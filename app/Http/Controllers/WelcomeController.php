<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Search\Aggregation;
use App\Helpers\GitHubLinkHelper;
use App\Services\HomepageCountsService;
use App\Services\Search\OpenSearchService;
use App\Services\Search\QueryBuilder;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function index(OpenSearchService $search, HomepageCountsService $counts): View
    {
        $monthlyStats = $this->prsOverTime($search, $dataMissing);

        $labelCounts = $counts->labelCounts();

        return view('welcome', [
            'monthlyStats' => $monthlyStats,
            'dataMissing' => $dataMissing,
            'paths' => $this->buildPaths($labelCounts),
            'areas' => $this->buildAreas($labelCounts),
            'links' => config('homepage.links'),
        ]);
    }

    /**
     * Monthly opened/closed PR counts feeding the single "Momentum" chart.
     *
     * @param  bool|null  $dataMissing  Set to true when the PR index is absent (dev only).
     * @return array<string, array{pr_opened: int, pr_closed: int}>
     */
    private function prsOverTime(OpenSearchService $search, ?bool &$dataMissing): array
    {
        $builder = new QueryBuilder;
        $builder
            ->addAggregation(new Aggregation(
                'prs_opened_per_month',
                [
                    'date_histogram' => [
                        'field' => 'created_at',
                        'calendar_interval' => 'month',
                        'format' => 'yyyy-MM',
                        'min_doc_count' => 0,
                    ],
                ]
            ))
            ->addAggregation(new Aggregation(
                'prs_closed_per_month',
                [
                    'date_histogram' => [
                        'field' => 'closed_at',
                        'calendar_interval' => 'month',
                        'format' => 'yyyy-MM',
                        'min_doc_count' => 0,
                    ],
                ]
            ))
            ->setSize(0);

        $dataMissing = false;

        try {
            $response = $search->searchPRs($builder);
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                abort(500, 'Error fetching PR data: '.$e->getMessage());
            }
            $response = [];
            $dataMissing = true;
        }

        $opened = $response['aggregations']['prs_opened_per_month']['buckets'] ?? [];
        $closed = $response['aggregations']['prs_closed_per_month']['buckets'] ?? [];

        $months = collect(array_merge(
            array_column($opened, 'key_as_string'),
            array_column($closed, 'key_as_string')
        ))->unique()->sort()->values();

        $stats = [];
        foreach ($months as $month) {
            $stats[$month] = ['pr_opened' => 0, 'pr_closed' => 0];
        }
        foreach ($opened as $bucket) {
            $stats[$bucket['key_as_string']]['pr_opened'] = $bucket['doc_count'];
        }
        foreach ($closed as $bucket) {
            $stats[$bucket['key_as_string']]['pr_closed'] = $bucket['doc_count'];
        }

        return $stats;
    }

    /**
     * Resolve the §3 "Choose how you want to help" path cards with live counts and links.
     *
     * @param  array<string, int>  $labelCounts
     * @return list<array{icon: string, title: string, blurb: string, cta: string, count: ?int, url: string}>
     */
    private function buildPaths(array $labelCounts): array
    {
        return array_map(static fn (array $path): array => [
            'icon' => $path['icon'],
            'title' => $path['title'],
            'blurb' => $path['blurb'],
            'cta' => $path['cta'],
            'count' => $labelCounts[$path['label']] ?? null,
            'url' => GitHubLinkHelper::issueLabelUrl($path['label']),
        ], config('homepage.paths'));
    }

    /**
     * Resolve the §4 "Pick your area" tiles. Labels resolving to zero open issues are
     * dropped so a renamed or emptied area degrades gracefully.
     *
     * @param  array<string, int>  $labelCounts
     * @return list<array{name: string, count: ?int, url: string}>
     */
    private function buildAreas(array $labelCounts): array
    {
        $areas = [];
        foreach (config('homepage.areas') as $label) {
            $count = $labelCounts[$label] ?? null;
            if ($count === 0) {
                continue;
            }
            $areas[] = [
                'name' => str_replace('Area: ', '', $label),
                'count' => $count,
                'url' => GitHubLinkHelper::issueLabelUrl($label),
            ];
        }

        return $areas;
    }
}
