<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\Services\Search\OpenSearchService;
use App\Support\BotFilter;
use Carbon\Carbon;
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
        // login => ['company' => string, 'updated_at' => string|null]. Tracking
        // the source document's updated_at lets a later index override an earlier
        // one when (and only when) it carries a more recent company value.
        $latest = [];

        foreach ([
            OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX,
            OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX,
        ] as $index) {
            $this->accumulate($index, $latest);
        }

        return array_map(fn (array $row): string => $row['company'], $latest);
    }

    /**
     * @param  array<string, array{company: string, updated_at: string|null}>  $latest
     */
    private function accumulate(string $index, array &$latest): void
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
                                        '_source' => ['author_company', 'updated_at'],
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
                if ($login === null) {
                    continue;
                }

                $source = $bucket['latest']['hits']['hits'][0]['_source'] ?? [];
                $company = $source['author_company'] ?? null;
                if (! is_string($company) || trim($company) === '') {
                    continue;
                }

                $updatedAt = isset($source['updated_at']) && is_string($source['updated_at']) ? $source['updated_at'] : null;

                if (! isset($latest[$login]) || $this->isNewer($updatedAt, $latest[$login]['updated_at'])) {
                    $latest[$login] = ['company' => $company, 'updated_at' => $updatedAt];
                }
            }

            $after = $aggregation['after_key'] ?? null;
        } while ($after !== null && $buckets !== []);
    }

    /**
     * Whether $candidate is strictly more recent than $current. A null candidate
     * never wins; a null current loses to any dated candidate. Ties keep the
     * incumbent (earlier index).
     */
    private function isNewer(?string $candidate, ?string $current): bool
    {
        if ($candidate === null) {
            return false;
        }

        if ($current === null) {
            return true;
        }

        return Carbon::parse($candidate)->greaterThan(Carbon::parse($current));
    }
}
