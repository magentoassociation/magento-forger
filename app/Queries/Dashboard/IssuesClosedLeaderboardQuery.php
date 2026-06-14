<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\DataTransferObjects\Dashboard\ContributorCount;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use OpenSearch\Client;

class IssuesClosedLeaderboardQuery
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return list<ContributorCount>
     */
    public function execute(Carbon $from, Carbon $to): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix('github-issues'),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['state' => 'CLOSED']],
                            ['range' => ['closed_at' => ['gte' => $from->toIso8601String(), 'lte' => $to->toIso8601String()]]],
                        ],
                        'must_not' => $this->botFilters(),
                    ],
                ],
                'aggs' => [
                    'by_contributor' => [
                        'terms' => [
                            'field' => 'author.keyword',
                            'size' => 100,
                            'order' => ['_count' => 'desc'],
                        ],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response);
    }

    /**
     * @return list<ContributorCount>
     */
    private function parseResponse(array $response): array
    {
        $results = [];
        foreach ($response['aggregations']['by_contributor']['buckets'] ?? [] as $bucket) {
            $results[] = new ContributorCount(login: $bucket['key'], count: $bucket['doc_count']);
        }

        return $results;
    }

    private function botFilters(): array
    {
        return [
            ['wildcard' => ['author.keyword' => 'engcom-*']],
            ['term' => ['author.keyword' => 'dependabot[bot]']],
            ['term' => ['author.keyword' => 'github-actions[bot]']],
            ['term' => ['author.keyword' => 'm2-assistant']],
        ];
    }
}
