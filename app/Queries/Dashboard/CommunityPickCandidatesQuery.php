<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Services\Search\OpenSearchService;
use OpenSearch\Client;

/**
 * Community Pick Candidates: open, non-draft PRs carrying none of the configured
 * excluded labels, ranked by 👍 reactions on the PR body, oldest first on ties.
 */
class CommunityPickCandidatesQuery
{
    public const PER_PAGE = 50;

    public function __construct(private readonly Client $client) {}

    /**
     * @return array{
     *     rows: list<array{number: int, title: string, url: string, author: ?string, created_at: string, thumbs_up_count: int, linked_issues: list<int>}>,
     *     total: int
     * }
     *
     * @throws \Exception When OpenSearch fails, including a missing index.
     */
    public function execute(int $page = 1): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX),
            'body' => [
                'from' => (max($page, 1) - 1) * self::PER_PAGE,
                'size' => self::PER_PAGE,
                'track_total_hits' => true,
                '_source' => ['id', 'title', 'url', 'author', 'created_at', 'thumbs_up_count', 'linked_issues'],
                'query' => ['bool' => [
                    'filter' => [['term' => ['is_open' => true]]],
                    'must_not' => [
                        ['term' => ['is_draft' => true]],
                        ['terms' => ['labels.keyword' => config('github.community_picked.exclude_labels', [])]],
                    ],
                ]],
                // unmapped_type: the field only exists once a sync has written it.
                'sort' => [
                    ['thumbs_up_count' => ['order' => 'desc', 'missing' => '_last', 'unmapped_type' => 'long']],
                    ['created_at' => ['order' => 'asc']],
                ],
            ],
        ]);

        return [
            'rows' => array_map(static fn (array $hit): array => [
                'number' => (int) $hit['_source']['id'],
                'title' => $hit['_source']['title'],
                'url' => $hit['_source']['url'],
                'author' => $hit['_source']['author'] ?? null,
                'created_at' => $hit['_source']['created_at'],
                'thumbs_up_count' => (int) ($hit['_source']['thumbs_up_count'] ?? 0),
                'linked_issues' => array_map('intval', $hit['_source']['linked_issues'] ?? []),
            ], $response['hits']['hits'] ?? []),
            'total' => (int) ($response['hits']['total']['value'] ?? 0),
        ];
    }
}
