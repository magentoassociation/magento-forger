<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\ClaimRecord;
use App\Services\Search\OpenSearchService;
use App\Support\BotFilter;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use OpenSearch\Client;

/**
 * Builds ClaimRecords from OpenSearch: maintainer self-assignments (review
 * requests) within the window, joined to when each PR entered the review pool
 * (pending-review label) and when the maintainer first reviewed it.
 */
class ClaimRecordReader
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return list<ClaimRecord>
     */
    public function read(CarbonInterface $from, CarbonInterface $to): array
    {
        $label = (string) config('leaderboard.pending_review_label', 'Progress: pending review');

        // 1. Self-assignments (ReviewRequestedEvent where actor == requested reviewer) in window.
        $claims = [];
        $this->scroll(
            OpenSearchService::OPENSEARCH_GITHUB_PR_TIMELINE_INDEX,
            [
                'bool' => [
                    'filter' => [
                        ['term' => ['type.keyword' => 'ReviewRequestedEvent']],
                        ['range' => [
                            'created_at' => [
                                'gte' => $from->toIso8601String(),
                                'lte' => $to->toIso8601String(),
                            ],
                        ]],
                    ],
                ],
            ],
            ['pr_number', 'actor', 'requested_reviewer', 'created_at'],
            function (array $source) use (&$claims): void {
                $maintainer = $source['requested_reviewer'] ?? null;

                if (empty($maintainer) || empty($source['pr_number']) || empty($source['created_at'])) {
                    return;
                }
                if ($maintainer !== ($source['actor'] ?? null) || $this->isBot($maintainer)) {
                    return;
                }

                $claims[] = [
                    'pr' => (int) $source['pr_number'],
                    'maintainer' => $maintainer,
                    'claimed_at' => Carbon::parse($source['created_at']),
                ];
            }
        );

        if ($claims === []) {
            return [];
        }

        // Collapse repeated self-assignments (assign → unassign → reassign) on the
        // same PR to one claim per (pr, maintainer) pair — the same identity used
        // in the reviewTimes lookup — keeping the earliest, so a maintainer can't
        // farm claim credit by re-requesting review on a PR they already claimed.
        $unique = [];
        foreach ($claims as $claim) {
            $key = $claim['pr'].'|'.$claim['maintainer'];
            if (! isset($unique[$key]) || $claim['claimed_at']->lessThan($unique[$key]['claimed_at'])) {
                $unique[$key] = $claim;
            }
        }
        $claims = array_values($unique);

        $prNumbers = array_values(array_unique(array_column($claims, 'pr')));
        $maintainers = array_values(array_unique(array_column($claims, 'maintainer')));

        $pendingReviewTimes = $this->pendingReviewTimes($prNumbers, $label);
        $reviewTimes = $this->reviewTimes($prNumbers, $maintainers);

        $records = [];
        foreach ($claims as $claim) {
            $records[] = new ClaimRecord(
                prNumber: $claim['pr'],
                maintainer: $claim['maintainer'],
                claimedAt: $claim['claimed_at'],
                pendingReviewAt: $this->latestBefore($pendingReviewTimes[$claim['pr']] ?? [], $claim['claimed_at']),
                firstReviewAt: $this->earliestAfter(
                    $reviewTimes[$claim['pr'].'|'.$claim['maintainer']] ?? [],
                    $claim['claimed_at'],
                ),
            );
        }

        return $records;
    }

    /**
     * pending-review LabeledEvent timestamps per PR (any time, not window-bound).
     *
     * @param  list<int>  $prNumbers
     * @return array<int, list<CarbonInterface>>
     */
    private function pendingReviewTimes(array $prNumbers, string $label): array
    {
        $times = [];

        foreach (array_chunk($prNumbers, 1000) as $chunk) {
            $this->scroll(
                OpenSearchService::OPENSEARCH_GITHUB_PR_TIMELINE_INDEX,
                [
                    'bool' => [
                        'filter' => [
                            ['term' => ['type.keyword' => 'LabeledEvent']],
                            ['term' => ['label_name.keyword' => $label]],
                            ['terms' => ['pr_number' => $chunk]],
                        ],
                    ],
                ],
                ['pr_number', 'created_at'],
                function (array $source) use (&$times): void {
                    if (empty($source['pr_number']) || empty($source['created_at'])) {
                        return;
                    }
                    $times[(int) $source['pr_number']][] = Carbon::parse($source['created_at']);
                }
            );
        }

        return $times;
    }

    /**
     * Review submission timestamps keyed by "pr|maintainer".
     *
     * @param  list<int>  $prNumbers
     * @param  list<string>  $maintainers
     * @return array<string, list<CarbonInterface>>
     */
    private function reviewTimes(array $prNumbers, array $maintainers): array
    {
        $times = [];

        foreach (array_chunk($prNumbers, 1000) as $chunk) {
            $this->scroll(
                OpenSearchService::OPENSEARCH_GITHUB_PR_REVIEWS_INDEX,
                [
                    'bool' => [
                        'filter' => [
                            ['terms' => ['pr_number' => $chunk]],
                            ['terms' => ['author.keyword' => $maintainers]],
                        ],
                    ],
                ],
                ['pr_number', 'author', 'submitted_at'],
                function (array $source) use (&$times): void {
                    if (empty($source['pr_number']) || empty($source['author']) || empty($source['submitted_at'])) {
                        return;
                    }
                    $times[$source['pr_number'].'|'.$source['author']][] = Carbon::parse($source['submitted_at']);
                }
            );
        }

        return $times;
    }

    /**
     * @param  list<CarbonInterface>  $times
     */
    private function latestBefore(array $times, CarbonInterface $cutoff): ?CarbonInterface
    {
        $latest = null;
        foreach ($times as $time) {
            if ($time->lessThanOrEqualTo($cutoff) && ($latest === null || $time->greaterThan($latest))) {
                $latest = $time;
            }
        }

        return $latest;
    }

    /**
     * @param  list<CarbonInterface>  $times
     */
    private function earliestAfter(array $times, CarbonInterface $cutoff): ?CarbonInterface
    {
        $earliest = null;
        foreach ($times as $time) {
            if ($time->greaterThanOrEqualTo($cutoff) && ($earliest === null || $time->lessThan($earliest))) {
                $earliest = $time;
            }
        }

        return $earliest;
    }

    private function isBot(string $login): bool
    {
        return BotFilter::isBot($login);
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
