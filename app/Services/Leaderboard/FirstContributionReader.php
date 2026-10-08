<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\Leaderboard;

use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use OpenSearch\Client;

/**
 * Per-author contribution dates across all time (not window-bound), used for
 * `first_contribution_at` and for detecting "comebacks" (a return after a long
 * silence).
 *
 * Scope: issues opened, PRs opened, and reviews submitted. Triage-only actions
 * (labels, claims) are not included.
 */
class FirstContributionReader
{
    public function __construct(private readonly Client $client) {}

    /**
     * Earliest contribution date per author, all time.
     *
     * @return array<string, CarbonInterface>
     */
    public function read(): array
    {
        $result = [];

        foreach ($this->sources() as [$index, $dateField]) {
            $this->accumulate($index, $dateField, 'min', null, $result);
        }

        return $result;
    }

    /**
     * Latest contribution date per author strictly before the given cutoff — i.e.
     * their last activity prior to the current window, used to size a comeback gap.
     *
     * @return array<string, CarbonInterface>
     */
    public function lastContributionBefore(CarbonInterface $before): array
    {
        $result = [];

        foreach ($this->sources() as [$index, $dateField]) {
            $this->accumulate($index, $dateField, 'max', $before, $result);
        }

        return $result;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function sources(): array
    {
        return [
            [OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX, 'created_at'],
            [OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX, 'created_at'],
            [OpenSearchService::OPENSEARCH_GITHUB_PR_REVIEWS_INDEX, 'submitted_at'],
        ];
    }

    /**
     * @param  'min'|'max'  $metric
     * @param  array<string, CarbonInterface>  $result
     */
    private function accumulate(
        string $index,
        string $dateField,
        string $metric,
        ?CarbonInterface $before,
        array &$result
    ): void
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

            $body = [
                'size' => 0,
                'aggs' => [
                    'authors' => [
                        'composite' => $composite,
                        'aggs' => ['value' => [$metric => ['field' => $dateField]]],
                    ],
                ],
            ];
            if ($before !== null) {
                $body['query'] = ['range' => [$dateField => ['lt' => $before->toIso8601String()]]];
            }

            $response = $this->client->search([
                'index' => OpenSearchService::getIndexWithPrefix($index),
                'body' => $body,
            ]);

            $aggregation = $response['aggregations']['authors'] ?? [];
            $buckets = $aggregation['buckets'] ?? [];

            foreach ($buckets as $bucket) {
                $login = $bucket['key']['author'] ?? null;
                $value = $bucket['value'] ?? [];

                if ($login === null || ($value['value'] ?? null) === null) {
                    continue;
                }

                $date = isset($value['value_as_string'])
                    ? Carbon::parse($value['value_as_string'])
                    : Carbon::createFromTimestampMs((int) $value['value']);

                if (! isset($result[$login])
                    || ($metric === 'min' ? $date->lessThan($result[$login]) : $date->greaterThan($result[$login]))
                ) {
                    $result[$login] = $date;
                }
            }

            $after = $aggregation['after_key'] ?? null;
        } while ($after !== null && $buckets !== []);
    }
}
