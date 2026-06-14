<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Services\Search\OpenSearchService;
use Carbon\Carbon;

class ReviewsRejectedLeaderboardQuery extends BaseReviewLeaderboardQuery
{
    /**
     * @return list<ContributorCount>
     */
    public function execute(Carbon $from, Carbon $to): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_PR_REVIEWS_INDEX),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['state.keyword' => 'CHANGES_REQUESTED']],
                            ['range' => ['submitted_at' => ['gte' => $from->toIso8601String(), 'lte' => $to->toIso8601String()]]],
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
}
