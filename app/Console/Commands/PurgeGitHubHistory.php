<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use OpenSearch\Client;

/**
 * Delete issues and PRs created before github.history_start, plus every document
 * hanging off them (reviews, PR timeline, issue events, interactions). The sync skips
 * the same items, so this is a one-off cleanup of data stored before the floor existed.
 */
class PurgeGitHubHistory extends Command
{
    /** Search page size; the pre-floor set is a few hundred numbers. */
    private const MAX_NUMBERS = 10000;

    protected $signature = 'sync:github:purge-history
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Delete synced GitHub issues and PRs created before github.history_start, and their related documents';

    public function handle(Client $client): int
    {
        $start = config('github.history_start');
        if (! $start) {
            $this->error('github.history_start is not set; nothing to purge against.');

            return 1;
        }

        $floor = Carbon::parse($start);

        try {
            $numbers = $this->numbersCreatedBefore($client, $floor);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        if ($numbers === []) {
            $this->info("No issues or PRs created before {$floor->toDateString()}. Nothing to purge.");

            return 0;
        }

        $count = count($numbers);
        if (! $this->option('force')
            && ! $this->confirm("Delete {$count} issues/PRs created before {$floor->toDateString()} and all their related documents?")) {
            $this->info('Operation canceled.');

            return 0;
        }

        // Issues and PRs share GitHub's number sequence, so one list covers both.
        $deletes = [
            OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX => ['ids' => ['values' => array_map('strval', $numbers)]],
            OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX => ['ids' => ['values' => array_map('strval', $numbers)]],
            OpenSearchService::OPENSEARCH_GITHUB_PR_REVIEWS_INDEX => ['terms' => ['pr_number' => $numbers]],
            OpenSearchService::OPENSEARCH_GITHUB_PR_TIMELINE_INDEX => ['terms' => ['pr_number' => $numbers]],
            OpenSearchService::OPENSEARCH_GITHUB_EVENTS_INDEX => ['terms' => ['issues-id' => $numbers]],
            OpenSearchService::OPENSEARCH_GITHUB_INTERACTIONS_INDEX => ['terms' => ['issues-id' => $numbers]],
        ];

        $failed = false;
        foreach ($deletes as $index => $query) {
            try {
                $response = $client->deleteByQuery([
                    'index' => OpenSearchService::getIndexWithPrefix($index),
                    'body' => ['query' => $query],
                    'conflicts' => 'proceed',
                    'refresh' => true,
                    'ignore_unavailable' => true,
                ]);
                $this->info("{$index}: deleted ".($response['deleted'] ?? 0).' documents.');
            } catch (\Exception $e) {
                $failed = true;
                $this->error("{$index}: delete failed: ".$e->getMessage());
            }
        }

        if ($failed) {
            return 1;
        }

        $this->info('Done. Run leaderboard:compute to rebuild scores without the purged history.');

        return 0;
    }

    /**
     * Numbers of every stored issue and PR created before the floor.
     *
     * @return list<int>
     *
     * @throws \RuntimeException when the set is too large to fetch in one page.
     */
    private function numbersCreatedBefore(Client $client, Carbon $floor): array
    {
        $response = $client->search([
            'index' => implode(',', [
                OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX),
                OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX),
            ]),
            'ignore_unavailable' => true,
            'body' => [
                'size' => self::MAX_NUMBERS,
                'track_total_hits' => true,
                '_source' => ['id'],
                'query' => ['range' => ['created_at' => ['lt' => $floor->toIso8601String()]]],
            ],
        ]);

        $total = $response['hits']['total']['value'] ?? 0;
        if ($total > self::MAX_NUMBERS) {
            throw new \RuntimeException("{$total} issues/PRs predate the floor; more than one page (".self::MAX_NUMBERS.'). Purge in batches.');
        }

        $numbers = array_map(static fn (array $hit): int => (int) $hit['_id'], $response['hits']['hits'] ?? []);

        return array_values(array_unique($numbers));
    }
}
