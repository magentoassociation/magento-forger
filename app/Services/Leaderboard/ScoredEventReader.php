<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\DataTransferObjects\Leaderboard\Board;
use App\DataTransferObjects\Leaderboard\ScoredEvent;
use App\Services\Search\OpenSearchService;
use App\Support\BotFilter;
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
        $repo = (string) config('github.repo');

        // Contributor: PRs opened
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX,
            $this->rangeQuery('created_at', $from, $to),
            ['author', 'created_at', 'title', 'url'],
            function (array $source) use (&$events): void {
                if (! empty($source['author']) && ! empty($source['created_at'])) {
                    $events[] = new ScoredEvent(
                        $source['author'],
                        Board::CONTRIBUTOR,
                        Action::PR_OPENED,
                        Carbon::parse($source['created_at']),
                        title: $source['title'] ?? null,
                        url: $source['url'] ?? null,
                    );
                }
            }
        );

        // Contributor: PRs merged (impact-weighted author bonus)
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX,
            $this->rangeQuery('merged_at', $from, $to, [['term' => ['state.keyword' => 'MERGED']]]),
            ['author', 'merged_at', 'created_at', 'additions', 'deletions', 'title', 'url'],
            function (array $source) use (&$events, $impactMin, $impactMax): void {
                if (! empty($source['author']) && ! empty($source['merged_at'])) {
                    $impact = LeaderboardScorer::impactFromSize(
                        (int) ($source['additions'] ?? 0),
                        (int) ($source['deletions'] ?? 0),
                        $impactMin,
                        $impactMax,
                    );
                    // Attribute org credit to the authoring date, not merged_at,
                    // which can land months later under a different employer.
                    $attributionDate = empty($source['created_at']) ? null : Carbon::parse($source['created_at']);
                    $events[] = new ScoredEvent(
                        $source['author'],
                        Board::CONTRIBUTOR,
                        Action::PR_MERGED,
                        Carbon::parse($source['merged_at']),
                        $impact,
                        $attributionDate,
                        title: $source['title'] ?? null,
                        url: $source['url'] ?? null,
                    );
                }
            }
        );

        // Contributor: issues opened
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX,
            $this->rangeQuery('created_at', $from, $to),
            ['author', 'created_at', 'title', 'url'],
            function (array $source) use (&$events): void {
                if (! empty($source['author']) && ! empty($source['created_at'])) {
                    $events[] = new ScoredEvent(
                        $source['author'],
                        Board::CONTRIBUTOR,
                        Action::ISSUE_OPENED,
                        Carbon::parse($source['created_at']),
                        title: $source['title'] ?? null,
                        url: $source['url'] ?? null,
                    );
                }
            }
        );

        // Contributor: issues resolved by a merged PR
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX,
            $this->rangeQuery('closed_at', $from, $to, [['term' => ['closed_by_merged_pr' => true]]]),
            ['author', 'closed_at', 'created_at', 'title', 'url'],
            function (array $source) use (&$events): void {
                if (! empty($source['author']) && ! empty($source['closed_at'])) {
                    // Attribute org credit to when the issue was opened, not its
                    // close date, which can fall under a later employer.
                    $attributionDate = empty($source['created_at']) ? null : Carbon::parse($source['created_at']);
                    $events[] = new ScoredEvent(
                        $source['author'],
                        Board::CONTRIBUTOR,
                        Action::ISSUE_RESOLVED_BY_MERGE,
                        Carbon::parse($source['closed_at']),
                        1.0,
                        $attributionDate,
                        title: $source['title'] ?? null,
                        url: $source['url'] ?? null,
                    );
                }
            }
        );

        // Maintainer: reviews — collect, then resolve each PR's author to drop
        // self-reviews (e.g. commenting on your own PR) before scoring.
        $stateAction = [
            'APPROVED' => Action::REVIEW_APPROVED,
            'CHANGES_REQUESTED' => Action::REVIEW_REJECTED,
            'COMMENTED' => Action::REVIEW_COMMENTED,
        ];
        $reviews = [];
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PR_REVIEWS_INDEX,
            $this->rangeQuery('submitted_at', $from, $to),
            ['author', 'state', 'submitted_at', 'pr_number'],
            function (array $source) use (&$reviews, $stateAction): void {
                $action = $stateAction[$source['state'] ?? ''] ?? null;
                if (
                    $action === null
                    || empty($source['author'])
                    || empty($source['submitted_at'])
                    || empty($source['pr_number'])
                ) {
                    return;
                }
                $reviews[] = [
                    'pr_number' => $source['pr_number'],
                    'author' => $source['author'],
                    'action' => $action,
                    'state' => $source['state'],
                    'submitted_at' => $source['submitted_at'],
                ];
            }
        );

        $events = array_merge($events, $this->reviewEvents($reviews, $impactMin, $impactMax, $repo));

        // Maintainer: triage (labels applied to issues and PRs)
        $events = array_merge($events, $this->labelAppliedEvents($from, $to, $repo));

        return $events;
    }

    /**
     * Triage scoring: one credit per (actor, target, label), so add/remove churn
     * can't farm points. Issue labels come from github-events, PR labels from
     * github-pr-timeline. Excluded labels (e.g. Adobe's pending-review label) and
     * bot actors are filtered out.
     *
     * @return list<ScoredEvent>
     */
    private function labelAppliedEvents(CarbonInterface $from, CarbonInterface $to, string $repo = ''): array
    {
        $excluded = (array) config('leaderboard.triage.excluded_labels', []);
        $rows = [];

        // Issue label events
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_EVENTS_INDEX,
            [
                'bool' => [
                    'filter' => [
                        ['term' => ['interaction_name.keyword' => 'labeled']],
                        ['range' => [
                            'interaction_date' => [
                                'gte' => $from->toIso8601String(),
                                'lte' => $to->toIso8601String(),
                            ],
                        ]],
                    ],
                    'must_not' => $this->botFiltersFor('github_account_name.keyword'),
                ],
            ],
            ['github_account_name', 'label_name', 'issues-id', 'interaction_date'],
            function (array $source) use (&$rows): void {
                if (empty($source['label_name']) || empty($source['interaction_date'])) {
                    return;
                }
                $rows[] = [
                    'actor' => $source['github_account_name'] ?? null,
                    'label' => $source['label_name'],
                    'target' => 'issue:'.($source['issues-id'] ?? ''),
                    'date' => Carbon::parse($source['interaction_date']),
                ];
            }
        );

        // PR label events
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PR_TIMELINE_INDEX,
            [
                'bool' => [
                    'filter' => [
                        ['term' => ['type.keyword' => 'LabeledEvent']],
                        ['range' => [
                            'created_at' => [
                                'gte' => $from->toIso8601String(),
                                'lte' => $to->toIso8601String(),
                            ],
                        ]],
                    ],
                    'must_not' => $this->botFiltersFor('actor.keyword'),
                ],
            ],
            ['actor', 'label_name', 'pr_number', 'created_at'],
            function (array $source) use (&$rows): void {
                if (empty($source['label_name']) || empty($source['created_at'])) {
                    return;
                }
                $rows[] = [
                    'actor' => $source['actor'] ?? null,
                    'label' => $source['label_name'],
                    'target' => 'pr:'.($source['pr_number'] ?? ''),
                    'date' => Carbon::parse($source['created_at']),
                ];
            }
        );

        return $this->buildLabelEvents($rows, $excluded, $repo);
    }

    /**
     * Deduplicate raw label rows into one scored event per (actor, target, label),
     * keeping the earliest application date. Pure — unit-tested.
     *
     * @param  list<array{actor: string|null, label: string, target: string, date: CarbonInterface}>  $rows
     * @param  list<string>  $excluded
     * @return list<ScoredEvent>
     */
    private function buildLabelEvents(array $rows, array $excluded, string $repo = ''): array
    {
        $byKey = [];

        foreach ($rows as $row) {
            $actor = $row['actor'] ?? null;
            $label = $row['label'] ?? null;
            $date = $row['date'] ?? null;

            if (empty($actor) || empty($label) || $date === null || in_array($label, $excluded, true)) {
                continue;
            }

            $key = $actor.'|'.$row['target'].'|'.$label;

            if (! isset($byKey[$key]) || $date->lessThan($byKey[$key]['date'])) {
                $byKey[$key] = ['actor' => $actor, 'date' => $date, 'target' => $row['target'], 'label' => $label];
            }
        }

        $events = [];
        foreach ($byKey as $entry) {
            [$title, $url] = $this->labelTargetDisplay($entry['target'], $entry['label'], $repo);
            $events[] = new ScoredEvent(
                $entry['actor'],
                Board::MAINTAINER,
                Action::LABEL_APPLIED,
                $entry['date'],
                title: $title,
                url: $url,
            );
        }

        return $events;
    }

    /**
     * Display title + URL for a labelled target. PR targets link to the PR;
     * issue targets carry only an internal id in the index, so they surface the
     * label name without a link.
     *
     * @return array{0: string, 1: string|null}
     */
    private function labelTargetDisplay(string $target, string $label, string $repo): array
    {
        if (str_starts_with($target, 'pr:')) {
            $number = substr($target, 3);

            return [
                "PR #{$number}",
                $repo !== '' && $number !== '' ? "https://github.com/{$repo}/pull/{$number}" : null,
            ];
        }

        return ["'{$label}' label", null];
    }

    /**
     * Score reviews, skipping self-reviews (reviewer == PR author). An approved
     * review whose PR later merged earns an additional impact-weighted bonus.
     *
     * @param  list<array{
     *     pr_number: int|string,
     *     author: string,
     *     action: Action,
     *     state: string,
     *     submitted_at: string
     * }>  $reviews
     * @return list<ScoredEvent>
     */
    private function reviewEvents(array $reviews, float $impactMin, float $impactMax, string $repo = ''): array
    {
        if ($reviews === []) {
            return [];
        }

        $prNumbers = array_values(array_unique(array_column($reviews, 'pr_number')));
        $prs = $this->pullRequestsInfo($prNumbers, $impactMin, $impactMax);

        $events = [];
        foreach ($reviews as $review) {
            $pr = $prs[$review['pr_number']] ?? null;

            // A maintainer doesn't earn points reviewing their own PR.
            if ($pr !== null && $pr['author'] === $review['author']) {
                continue;
            }

            $title = 'PR #'.$review['pr_number'];
            $url = $repo !== '' ? "https://github.com/{$repo}/pull/{$review['pr_number']}" : null;

            $events[] = new ScoredEvent(
                $review['author'],
                Board::MAINTAINER,
                $review['action'],
                Carbon::parse($review['submitted_at']),
                title: $title,
                url: $url,
            );

            if ($review['state'] === 'APPROVED' && $pr !== null && $pr['merged']) {
                $events[] = new ScoredEvent(
                    $review['author'],
                    Board::MAINTAINER,
                    Action::APPROVED_THEN_MERGED,
                    Carbon::parse($review['submitted_at']),
                    $pr['impact'],
                    title: $title,
                    url: $url,
                );
            }
        }

        return $events;
    }

    /**
     * Author, merge status and impact for the given PRs, keyed by PR number.
     *
     * @param  list<int|string>  $prNumbers
     * @return array<int|string, array{author: string|null, merged: bool, impact: float}>
     */
    private function pullRequestsInfo(array $prNumbers, float $impactMin, float $impactMax): array
    {
        $info = [];

        foreach (array_chunk($prNumbers, 1000) as $chunk) {
            $response = $this->client->search([
                'index' => OpenSearchService::getIndexWithPrefix(
                    OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX,
                ),
                'body' => [
                    'size' => count($chunk),
                    '_source' => ['id', 'author', 'state', 'additions', 'deletions'],
                    'query' => ['bool' => ['filter' => [['terms' => ['id' => $chunk]]]]],
                ],
            ]);

            foreach ($response['hits']['hits'] ?? [] as $hit) {
                $source = $hit['_source'] ?? [];
                if (! isset($source['id'])) {
                    continue;
                }

                $info[$source['id']] = [
                    'author' => $source['author'] ?? null,
                    'merged' => ($source['state'] ?? null) === 'MERGED',
                    'impact' => LeaderboardScorer::impactFromSize(
                        (int) ($source['additions'] ?? 0),
                        (int) ($source['deletions'] ?? 0),
                        $impactMin,
                        $impactMax,
                    ),
                ];
            }
        }

        return $info;
    }

    /**
     * @param  list<array<string, mixed>>  $extraFilters
     * @return array<string, mixed>
     */
    private function rangeQuery(
        string $dateField,
        CarbonInterface $from,
        CarbonInterface $to,
        array $extraFilters = []
    ): array {
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
        return $this->botFiltersFor('author.keyword');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function botFiltersFor(string $field): array
    {
        return BotFilter::mustNot($field);
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
