<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\DataTransferObjects\Dashboard\ContributorCount;
use OpenSearch\Client;

abstract class BaseReviewLeaderboardQuery
{
    public function __construct(protected readonly Client $client) {}

    /**
     * @return list<ContributorCount>
     */
    protected function parseResponse(array $response): array
    {
        $results = [];
        foreach ($response['aggregations']['by_contributor']['buckets'] ?? [] as $bucket) {
            $results[] = new ContributorCount(login: $bucket['key'], count: $bucket['doc_count']);
        }

        return $results;
    }

    protected function botFilters(): array
    {
        return [
            ['wildcard' => ['author.keyword' => 'engcom-*']],
            ['term' => ['author.keyword' => 'dependabot[bot]']],
            ['term' => ['author.keyword' => 'github-actions[bot]']],
            ['term' => ['author.keyword' => 'm2-assistant']],
        ];
    }
}
