<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use OpenSearch\Client;

/**
 * Reads scored events from OpenSearch within a date window. Bots are excluded at
 * query time using the same list as the raw-count leaderboards.
 */
class ScoredEventReader
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return list<ScoredEvent>
     */
    public function read(CarbonInterface $from, CarbonInterface $to): array
    {
        $events = [];
        $impactMin = (float) config('leaderboard.impact.min', 1.0);
        $impactMax = (float) config('leaderboard.impact.max', 5.0);

        // Contributor: PRs opened
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX,
            $this->rangeQuery('created_at', $from, $to),
            ['author', 'created_at'],
            function (array $source) use (&$events): void {
                if (! empty($source['author']) && ! empty($source['created_at'])) {
                    $events[] = new ScoredEvent($source['author'], Board::CONTRIBUTOR, 'pr_opened', Carbon::parse($source['created_at']));
                }
            }
        );

        // Contributor: PRs merged (impact-weighted author bonus)
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX,
            $this->rangeQuery('merged_at', $from, $to, [['term' => ['state.keyword' => 'MERGED']]]),
            ['author', 'merged_at', 'additions', 'deletions'],
            function (array $source) use (&$events, $impactMin, $impactMax): void {
                if (! empty($source['author']) && ! empty($source['merged_at'])) {
                    $impact = LeaderboardScorer::impactFromSize(
                        (int) ($source['additions'] ?? 0),
                        (int) ($source['deletions'] ?? 0),
                        $impactMin,
                        $impactMax,
                    );
                    $events[] = new ScoredEvent($source['author'], Board::CONTRIBUTOR, 'pr_merged', Carbon::parse($source['merged_at']), $impact);
                }
            }
        );

        // Contributor: issues opened
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX,
            $this->rangeQuery('created_at', $from, $to),
            ['author', 'created_at'],
            function (array $source) use (&$events): void {
                if (! empty($source['author']) && ! empty($source['created_at'])) {
                    $events[] = new ScoredEvent($source['author'], Board::CONTRIBUTOR, 'issue_opened', Carbon::parse($source['created_at']));
                }
            }
        );

        // Contributor: issues resolved by a merged PR
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX,
            $this->rangeQuery('closed_at', $from, $to, [['term' => ['closed_by_merged_pr' => true]]]),
            ['author', 'closed_at'],
            function (array $source) use (&$events): void {
                if (! empty($source['author']) && ! empty($source['closed_at'])) {
                    $events[] = new ScoredEvent($source['author'], Board::CONTRIBUTOR, 'issue_resolved_by_merge', Carbon::parse($source['closed_at']));
                }
            }
        );

        // Maintainer: reviews
        $stateAction = [
            'APPROVED' => 'review_approved',
            'CHANGES_REQUESTED' => 'review_rejected',
            'COMMENTED' => 'review_commented',
        ];
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PR_REVIEWS_INDEX,
            $this->rangeQuery('submitted_at', $from, $to),
            ['author', 'state', 'submitted_at'],
            function (array $source) use (&$events, $stateAction): void {
                $action = $stateAction[$source['state'] ?? ''] ?? null;
                if ($action !== null && ! empty($source['author']) && ! empty($source['submitted_at'])) {
                    $events[] = new ScoredEvent($source['author'], Board::MAINTAINER, $action, Carbon::parse($source['submitted_at']));
                }
            }
        );

        return $events;
    }

    /**
     * @param  list<array<string, mixed>>  $extraFilters
     * @return array<string, mixed>
     */
    private function rangeQuery(string $dateField, CarbonInterface $from, CarbonInterface $to, array $extraFilters = []): array
    {
        return [
            'bool' => [
                'filter' => array_merge(
                    [['range' => [$dateField => ['gte' => $from->toIso8601String(), 'lte' => $to->toIso8601String()]]]],
                    $extraFilters,
                ),
                'must_not' => $this->botFilters(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function botFilters(): array
    {
        return [
            ['wildcard' => ['author.keyword' => 'engcom-*']],
            ['term' => ['author.keyword' => 'dependabot[bot]']],
            ['term' => ['author.keyword' => 'github-actions[bot]']],
            ['term' => ['author.keyword' => 'm2-assistant']],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $source
     */
    private function scroll(string $index, array $query, array $source, callable $handle): void
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix($index),
            'scroll' => '1m',
            'size' => 1000,
            '_source' => $source,
            'body' => ['query' => $query],
        ]);

        $scrollId = $response['_scroll_id'] ?? null;
        $hits = $response['hits']['hits'] ?? [];

        try {
            while (! empty($hits)) {
                foreach ($hits as $hit) {
                    $handle($hit['_source'] ?? []);
                }

                if ($scrollId === null) {
                    break;
                }

                $response = $this->client->scroll(['scroll_id' => $scrollId, 'scroll' => '1m']);
                $scrollId = $response['_scroll_id'] ?? null;
                $hits = $response['hits']['hits'] ?? [];
            }
        } finally {
            if ($scrollId !== null) {
                try {
                    $this->client->clearScroll(['scroll_id' => $scrollId]);
                } catch (\Throwable) {
                    // Best-effort cleanup; the scroll context expires on its own TTL.
                }
            }
        }
    }
}
