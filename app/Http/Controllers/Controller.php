<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Search\Aggregation;
use App\Queries\Dashboard\AgeOverTimeQuery;
use App\Services\Search\QueryBuilder;

abstract class Controller
{
    /**
     * Monthly opened/closed counts for one index, feeding the homepage Momentum charts
     * and the By Month all-time chart.
     *
     * Returns null on any search failure other than a missing index, so the page hides
     * that chart instead of failing.
     *
     * @param  callable(QueryBuilder): array<string, mixed>  $run  Runs the query against the PR or issue index.
     * @param  string  $prefix  Aggregation name prefix ('prs' or 'issues').
     * @param  bool  $dataMissing  Set to true when the index is absent (dev only); never reset.
     * @return array<string, array{opened: int, closed: int}>|null
     */
    protected function openedClosedPerMonth(callable $run, string $prefix, bool &$dataMissing): ?array
    {
        $builder = new QueryBuilder;
        $builder
            ->addAggregation(new Aggregation(
                "{$prefix}_opened_per_month",
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
                "{$prefix}_closed_per_month",
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

        try {
            $response = $run($builder);
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                report($e);

                return null;
            }
            $response = [];
            $dataMissing = true;
        }

        $opened = $response['aggregations']["{$prefix}_opened_per_month"]['buckets'] ?? [];
        $closed = $response['aggregations']["{$prefix}_closed_per_month"]['buckets'] ?? [];

        $months = collect(array_merge(
            array_column($opened, 'key_as_string'),
            array_column($closed, 'key_as_string')
        ))->unique()->sort()->values();

        $stats = [];
        foreach ($months as $month) {
            $stats[$month] = ['opened' => 0, 'closed' => 0];
        }
        foreach ($opened as $bucket) {
            $stats[$bucket['key_as_string']]['opened'] = $bucket['doc_count'];
        }
        foreach ($closed as $bucket) {
            $stats[$bucket['key_as_string']]['closed'] = $bucket['doc_count'];
        }

        return $stats;
    }

    /**
     * Average age at close per month for one index, or null when the search fails so
     * the page hides the chart instead of failing. A missing index is expected in dev
     * and not reported.
     *
     * @return array<string, int|null>|null
     */
    protected function ageOverTime(AgeOverTimeQuery $query, string $index): ?array
    {
        try {
            return $query->execute($index);
        } catch (\Exception $e) {
            if (! $this->isMissingIndex($e)) {
                report($e);
            }

            return null;
        }
    }

    protected function isMissingIndex(\Exception $e): bool
    {
        return str_contains($e->getMessage(), 'index_not_found_exception')
            || str_contains($e->getMessage(), 'no such index');
    }
}
