<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\Services\Search\OpenSearchService;
use App\Support\BotFilter;
use OpenSearch\Client;

/**
 * Each non-bot author's most recent GitHub profile `company` string, harvested
 * from PR and issue documents. Used to seed low-confidence org memberships.
 */
class AuthorCompanyReader
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<string, string> login => raw company string (non-empty)
     */
    public function read(): array
    {
        $companies = [];

        foreach ([
            OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX,
            OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX,
        ] as $index) {
            $this->accumulate($index, $companies);
        }

        return $companies;
    }

    /**
     * @param  array<string, string>  $companies
     */
    private function accumulate(string $index, array &$companies): void
    {
        $after = null;

        do {
            $composite = [
                'size' => 1000,
                'sources' => [['author' => ['terms' => ['field' => 'author.keyword']]]],
            ];
            if ($after !== null) {
                $composite['after'] = $after;
            }

            $response = $this->client->search([
                'index' => OpenSearchService::getIndexWithPrefix($index),
                'body' => [
                    'size' => 0,
                    'query' => [
                        'bool' => [
                            'filter' => [['exists' => ['field' => 'author_company']]],
                            'must_not' => BotFilter::mustNot('author.keyword'),
                        ],
                    ],
                    'aggs' => [
                        'authors' => [
                            'composite' => $composite,
                            'aggs' => [
                                'latest' => [
                                    'top_hits' => [
                                        'size' => 1,
                                        '_source' => ['author_company'],
                                        'sort' => [['updated_at' => ['order' => 'desc']]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            $aggregation = $response['aggregations']['authors'] ?? [];
            $buckets = $aggregation['buckets'] ?? [];

            foreach ($buckets as $bucket) {
                $login = $bucket['key']['author'] ?? null;
                if ($login === null || isset($companies[$login])) {
                    continue;
                }

                $company = $bucket['latest']['hits']['hits'][0]['_source']['author_company'] ?? null;
                if (is_string($company) && trim($company) !== '') {
                    $companies[$login] = $company;
                }
            }

            $after = $aggregation['after_key'] ?? null;
        } while ($after !== null && $buckets !== []);
    }
}
