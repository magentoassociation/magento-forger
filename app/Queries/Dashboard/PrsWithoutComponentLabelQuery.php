<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Services\Search\OpenSearchService;
use DateTime;
use Illuminate\Support\Facades\Log;
use OpenSearch\Client;

class PrsWithoutComponentLabelQuery
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<string, array{year: string, total: int, months: array<string, array{month_number: string, total: int, start: string|null, end: string|null}>}>
     */
    public function execute(): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix('github-pull-requests'),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['is_open' => true]],
                        ],
                        'must_not' => [
                            ['regexp' => ['labels.keyword' => 'Component:.*']],
                        ],
                    ],
                ],
                'aggs' => [
                    'by_year' => [
                        'date_histogram' => [
                            'field' => 'created_at',
                            'calendar_interval' => 'year',
                            'format' => 'yyyy',
                            'order' => ['_key' => 'asc'],
                            'min_doc_count' => 1,
                        ],
                        'aggs' => [
                            'by_month' => [
                                'date_histogram' => [
                                    'field' => 'created_at',
                                    'calendar_interval' => 'month',
                                    'format' => 'MM',
                                    'order' => ['_key' => 'asc'],
                                    'min_doc_count' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = [];
        foreach ($response['aggregations']['by_year']['buckets'] as $yearBucket) {
            $result[$yearBucket['key_as_string']] = [
                'year' => $yearBucket['key_as_string'],
                'total' => $yearBucket['doc_count'],
                'months' => $this->emptyMonthSkeleton(),
            ];
            foreach ($yearBucket['by_month']['buckets'] as $monthBucket) {
                $monthDate = DateTime::createFromFormat(
                    'Y-m-d',
                    $yearBucket['key_as_string'].'-'.$monthBucket['key_as_string'].'-01'
                );
                $result[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['total'] =
                    $monthBucket['doc_count'];
                if (! $monthDate instanceof DateTime) {
                    Log::warning('Skipping PR month date range because the bucket date could not be parsed.', [
                        'year' => $yearBucket['key_as_string'],
                        'month' => $monthBucket['key_as_string'],
                    ]);

                    continue;
                }
                $result[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['start'] =
                    (clone $monthDate)->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d\TH:i:s\Z');
                $result[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['end'] =
                    (clone $monthDate)->modify('last day of this month')->setTime(23, 59, 59)->format('Y-m-d\TH:i:s\Z');
            }
        }

        return $result;
    }

    /**
     * @return array<string, array{month_number: string, total: int, start: null, end: null}>
     */
    private function emptyMonthSkeleton(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $key = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $months[$key] = ['month_number' => $key, 'total' => 0, 'start' => null, 'end' => null];
        }

        return $months;
    }
}
