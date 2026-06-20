<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

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
        $repo = config('github.repo');

        if (! $repo || ! str_contains($repo, '/')) {
            $this->error('Missing or invalid repository. Set it in config/github.php');

            return 1;
        }

        [$owner, $name] = explode('/', $repo);
        $cursor = $this->option('cursor');
        $since = $this->option('since');
        $maxPages = $this->option('max-pages') ? (int) $this->option('max-pages') : null;
        $cutoff = null;

        if ($since) {
            try {
                $cutoff = Carbon::parse($since);
            } catch (Throwable) {
                $this->error("Invalid date format for --since option: $since");

                return 1;
            }
            $this->info('Filtering events for issues updated since: '.$cutoff->toDateTimeString());
        } else {
            $this->info('No date filter applied');
        }

        $totalPages = null;
        if ($cutoff === null) {
            try {
                $totalCounts = $gitHubIssueService->fetchIssueCount($owner, $name);
                $this->info("Syncing events for $repo. ({$totalCounts->summary()})");
                $totalPages = (int) ceil($totalCounts->total / 25);
                if ($maxPages !== null) {
                    $totalPages = min($totalPages, $maxPages);
                }
            } catch (Throwable $e) {
                $this->warn('Could not retrieve issue count');
                Log::warning('GitHub issue count failed', ['exception' => $e]);
            }
        }

        if ($cursor) {
            $this->info("Resuming from cursor: $cursor");
        }

        $pagesProcessed = 0;

        $result = $syncer->sync(
            fetchPage: function (?string $c) use ($gitHubIssueService, $owner, $name, $maxPages, &$pagesProcessed) {
                $pagesProcessed++;
                $response = $gitHubIssueService->fetchIssuesWithEvents($owner, $name, $c);
                if ($maxPages !== null && $pagesProcessed >= $maxPages) {
                    $response['pageInfo']['hasNextPage'] = false;
                }

                return $response;
            },
            index: function (array $nodes) use ($github, $openSearch, $cutoff) {
                $documents = [];
                foreach ($nodes as $issue) {
                    $issueNumber = $issue['number'];
                    foreach ($github->extractEventsFromIssue($issue) as $event) {
                        if ($cutoff && Carbon::parse($event['created_at'])->lt($cutoff)) {
                            continue;
                        }
                        $documents[] = [
                            'github_account_name' => $event['actor'],
                            'interaction_name' => $event['type'],
                            'issues-id' => $issueNumber,
                            'interaction_date' => Carbon::parse($event['created_at'])->toIso8601String(),
                        ];
                    }
                }
                if (! empty($documents)) {
                    $openSearch->indexBulk(
                        OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_EVENTS_INDEX),
                        $documents
                    );
                }
            },
            cutoff: $cutoff,
            cursor: $cursor,
            onPage: function (int $page, ?string $c) use ($totalPages) {
                $this->info('Page '.$page.($totalPages ? ' of '.$totalPages : '').' done. Cursor: '.$c);
            },
            onNode: fn ($issue) => $this->line("#{$issue['number']}: {$issue['title']} ({$issue['state']})"),
            onError: function (Throwable $e, int $page) {
                $this->warn("Could not fetch events for page $page");
                Log::error("Failed to process events page $page", ['exception' => $e]);
            },
        );

        if ($result['cutoffReached'] && $cutoff !== null) {
            $this->info('Last issue is older than given cutoff ('.$cutoff->toDateTimeString().'), stopping sync.');
        }

        $this->info('Done syncing GitHub events.');

        return 0;
    }
}
