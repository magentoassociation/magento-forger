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

/**
 * Sync all GitHub interactions (comments, reactions, etc.)
 * from issues into the "interactions" OpenSearch index.
 */
class SyncGitHubInteractions extends Command implements Isolatable
{
    protected $signature = 'sync:github:interactions
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Only import issues updated since this date (e.g. "2 weeks", "5 days")}
                            {--max-pages= : Maximum number of pages to process (default: all)}';

    protected $description = 'Sync all GitHub interactions into OpenSearch';

    public function handle(
        GitHubInteractionService $github,
        GitHubIssueService $gitHubIssueService,
        GitHubSyncer $syncer,
        OpenSearchService $openSearch
    ): int {
        $repo = config('github.repo');

        $parts = explode('/', (string) $repo);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            $this->error('Missing or invalid repository. Set it in config/github.php');

            return 1;
        }

        [$owner, $name] = $parts;
        $cursor = $this->option('cursor');
        $since = $this->option('since');
        $maxPagesOption = $this->option('max-pages');
        $maxPages = null;
        if ($maxPagesOption !== null) {
            if (! is_numeric($maxPagesOption) || (int) $maxPagesOption <= 0) {
                $this->error('--max-pages must be a positive integer.');

                return 1;
            }
            $maxPages = (int) $maxPagesOption;
        }
        $cutoff = null;

        if ($since) {
            try {
                $cutoff = Carbon::parse($since);
            } catch (Throwable) {
                $this->error("Invalid date format for --since option: $since");

                return 1;
            }
            $this->info('Filtering interactions updated since: '.$cutoff->toDateTimeString());
        } else {
            $this->info('No date filter applied');
        }

        $totalPages = null;
        if ($cutoff === null) {
            try {
                $totalCounts = $gitHubIssueService->fetchIssueCount($owner, $name);
                $this->info("Syncing interactions for $repo. ({$totalCounts->summary()})");
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
                $response = $gitHubIssueService->fetchIssuesWithInteractions($owner, $name, $c);
                if ($maxPages !== null && $pagesProcessed >= $maxPages) {
                    $response['pageInfo']['hasNextPage'] = false;
                }

                return $response;
            },
            index: function (array $nodes) use ($github, $openSearch, $owner, $name) {
                $interactions = [];
                foreach ($nodes as $issue) {
                    $issueId = $issue['number'];
                    foreach ($github->fetchAllInteractionsFromIssue($issue, $owner, $name) as $interaction) {
                        $interactions[] = [
                            'github_account_name' => $interaction['author'] ?? 'unknown',
                            'interaction_name' => $interaction['type'],
                            'issues-id' => $issueId,
                            'interaction_date' => Carbon::parse($interaction['date'])->toIso8601String(),
                        ];
                    }
                }
                if (! empty($interactions)) {
                    $openSearch->indexBulk(
                        OpenSearchService::OPENSEARCH_GITHUB_INTERACTIONS_INDEX,
                        $interactions
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
                $this->warn("Could not fetch interactions for page $page");
                Log::warning('GitHub interaction sync failed', ['exception' => $e]);
            },
        );

        if ($result['cutoffReached'] && $cutoff !== null) {
            $this->info('Last issue is older than given cutoff ('.$cutoff->toDateTimeString().'), stopping sync.');
        }

        $this->info('Done syncing interactions.');

        return 0;
    }
}
