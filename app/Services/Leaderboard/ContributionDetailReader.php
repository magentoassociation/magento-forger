<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ContributionItem;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use OpenSearch\Client;

/**
 * The individual issues/PRs/reviews behind one contributor's score within the
 * window — re-derived on demand for the drill-down. Covers authored items
 * (PRs/issues opened, PRs merged, issues resolved by merge) and reviews. Derived
 * bonuses (claim, label, merge-approval) are reflected in the score but not
 * itemized here.
 */
class ContributionDetailReader
{
    private const MAX_PER_TYPE = 500;

    public function __construct(private readonly Client $client) {}

    /**
     * @return list<ContributionItem>
     */
    public function readForLogin(string $login, CarbonInterface $from, CarbonInterface $to): array
    {
        $items = [];
        $repo = (string) config('github.repo');
        $impactMin = (float) config('leaderboard.impact.min', 1.0);
        $impactMax = (float) config('leaderboard.impact.max', 5.0);

        foreach ($this->search(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX, $login, 'created_at', $from, $to, ['title', 'url', 'created_at']) as $s) {
            $items[] = new ContributionItem(Board::CONTRIBUTOR, Action::PR_OPENED, Carbon::parse($s['created_at']), 1.0, $s['title'] ?? '', $s['url'] ?? '');
        }

        foreach ($this->search(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX, $login, 'merged_at', $from, $to, ['title', 'url', 'merged_at', 'additions', 'deletions'], [['term' => ['state.keyword' => 'MERGED']]]) as $s) {
            $impact = LeaderboardScorer::impactFromSize((int) ($s['additions'] ?? 0), (int) ($s['deletions'] ?? 0), $impactMin, $impactMax);
            $items[] = new ContributionItem(Board::CONTRIBUTOR, Action::PR_MERGED, Carbon::parse($s['merged_at']), $impact, $s['title'] ?? '', $s['url'] ?? '');
        }

        foreach ($this->search(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX, $login, 'created_at', $from, $to, ['title', 'url', 'created_at']) as $s) {
            $items[] = new ContributionItem(Board::CONTRIBUTOR, Action::ISSUE_OPENED, Carbon::parse($s['created_at']), 1.0, $s['title'] ?? '', $s['url'] ?? '');
        }

        foreach ($this->search(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX, $login, 'closed_at', $from, $to, ['title', 'url', 'closed_at'], [['term' => ['closed_by_merged_pr' => true]]]) as $s) {
            $items[] = new ContributionItem(Board::CONTRIBUTOR, Action::ISSUE_RESOLVED_BY_MERGE, Carbon::parse($s['closed_at']), 1.0, $s['title'] ?? '', $s['url'] ?? '');
        }

        $stateAction = [
            'APPROVED' => Action::REVIEW_APPROVED,
            'CHANGES_REQUESTED' => Action::REVIEW_REJECTED,
            'COMMENTED' => Action::REVIEW_COMMENTED,
        ];
        foreach ($this->search(OpenSearchService::OPENSEARCH_GITHUB_PR_REVIEWS_INDEX, $login, 'submitted_at', $from, $to, ['pr_number', 'state', 'submitted_at']) as $s) {
            $action = $stateAction[$s['state'] ?? ''] ?? null;
            if ($action === null || empty($s['pr_number'])) {
                continue;
            }
            $items[] = new ContributionItem(
                Board::MAINTAINER,
                $action,
                Carbon::parse($s['submitted_at']),
                1.0,
                'PR #'.$s['pr_number'],
                "https://github.com/{$repo}/pull/{$s['pr_number']}",
            );
        }

        return $items;
    }

    /**
     * @param  list<string>  $source
     * @param  list<array<string, mixed>>  $extraFilters
     * @return list<array<string, mixed>>
     */
    private function search(string $index, string $login, string $dateField, CarbonInterface $from, CarbonInterface $to, array $source, array $extraFilters = []): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix($index),
            'body' => [
                'size' => self::MAX_PER_TYPE,
                '_source' => $source,
                'sort' => [[$dateField => ['order' => 'desc']]],
                'query' => [
                    'bool' => [
                        'filter' => array_merge([
                            ['term' => ['author.keyword' => $login]],
                            ['range' => [$dateField => ['gte' => $from->toIso8601String(), 'lte' => $to->toIso8601String()]]],
                        ], $extraFilters),
                    ],
                ],
            ],
        ]);

        return array_map(fn (array $hit): array => $hit['_source'] ?? [], $response['hits']['hits'] ?? []);
    }
}
