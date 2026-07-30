<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SyncsWithGitHub;
use App\Exceptions\InvalidSyncCutoffException;
use App\Services\GitHub\GitHubInteractionService;
use App\Services\GitHub\GitHubIssueService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGitHubEvents extends Command implements Isolatable
{
    use SyncsWithGitHub;

    protected $signature = 'sync:github:events
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Only import issues updated since this date (e.g. "2 weeks", "5 days")}
                            {--max-pages= : Maximum number of pages to process (default: all)}';

    protected $description = 'Sync GitHub issue events into OpenSearch';

    public function handle(
        GitHubInteractionService $github,
        GitHubIssueService $gitHubIssueService,
        GitHubSyncer $syncer,
        OpenSearchService $openSearch
    ): int {
        if (($parts = $this->resolveRepository()) === null) {
            return 1;
        }

        [$owner, $name] = $parts;
        $cursor = $this->option('cursor');
        $maxPagesOption = $this->option('max-pages');
        $maxPages = null;

        if ($maxPagesOption !== null) {
            if (! is_numeric($maxPagesOption) || (int) $maxPagesOption <= 0) {
                $this->error('--max-pages must be a positive integer.');

                return 1;
            }

            $maxPages = (int) $maxPagesOption;
        }

        try {
            $cutoff = $this->parseCutoff('Filtering events for issues updated since');
        } catch (InvalidSyncCutoffException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $totalPages = null;

        if ($cutoff === null) {
            try {
                $totalCounts = $gitHubIssueService->fetchIssueCount($owner, $name);
                $this->info("Syncing events for {$owner}/{$name}. ({$totalCounts->summary()})");
                $totalPages = (int) ceil($totalCounts->total / 25);
                if ($maxPages !== null) {
                    $totalPages = min($totalPages, $maxPages);
                }
            } catch (Throwable $e) {
                $this->warn('Could not retrieve issue count');
                Log::warning('GitHub issue count failed', ['exception' => $e]);
            }
        }

        $this->reportCursorResume($cursor);

        $pagesProcessed = 0;
        $errorOccurred = false;

        $result = $syncer->sync(
            fetchPage: function (?string $c) use ($gitHubIssueService, $owner, $name, $maxPages, &$pagesProcessed) {
                $pagesProcessed++;
                $response = $gitHubIssueService->fetchIssuesWithEvents($owner, $name, $c);
                if ($maxPages !== null && $pagesProcessed >= $maxPages) {
                    $response['pageInfo']['hasNextPage'] = false;
                }

                return $response;
            },
            index: function (array $nodes) use ($github, $openSearch, $cutoff, $owner, $name) {
                $documents = [];
                foreach ($nodes as $issue) {
                    $issueNumber = $issue['number'];
                    foreach ($github->fetchAllEventsFromIssue($issue, $owner, $name) as $event) {
                        if ($cutoff && Carbon::parse($event['created_at'])->lt($cutoff)) {
                            continue;
                        }
                        $document = [
                            'github_account_name' => $event['actor'],
                            'interaction_name' => $event['type'],
                            'issues-id' => $issueNumber,
                            'interaction_date' => Carbon::parse($event['created_at'])->toIso8601String(),
                        ];
                        if (! empty($event['label'])) {
                            $document['label_name'] = $event['label'];
                        }
                        $documents[] = $document;
                    }
                }
                if (! empty($documents)) {
                    $openSearch->indexBulk(
                        OpenSearchService::OPENSEARCH_GITHUB_EVENTS_INDEX,
                        $documents
                    );
                }
            },
            cutoff: $cutoff,
            cursor: $cursor,
            onPage: $this->makeOnPageCallback($totalPages),
            onNode: $this->makeOnNodeCallback(),
            onError: $this->makeOnErrorCallback(
                $errorOccurred,
                fn ($e, $page) => Log::error("Failed to process events page $page", ['exception' => $e]),
            ),
        );

        $this->reportCutoffReached($result, $cutoff, 'issue');
        $this->reportDone($errorOccurred, 'Done syncing GitHub events.');

        return $errorOccurred ? 1 : 0;
    }
}
