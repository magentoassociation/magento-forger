<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GitHub\GitHubService;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGitHubEvents extends Command
{
    protected $signature = 'sync:github:events
                            {--since= : Only import issues updated since this relative time (e.g. "2 weeks", "5 days")}
                            {--max-pages= : Maximum number of pages to process (default: all)}';

    protected $description = 'Sync GitHub issue/PR events into OpenSearch';

    public function handle(GitHubService $github, OpenSearchService $openSearch): int
    {
        $repo = config('github.repo', 'magento/magento2');

        if (!str_contains($repo, '/')) {
            $this->error('Invalid repository. Expected format: owner/repo');

            return 1;
        }

        [$owner, $name] = explode('/', $repo);
        $sinceOption = $this->option('since');
        $maxPages = $this->option('max-pages') ? (int)$this->option('max-pages') : null;
        $cutoff = null;

        if ($sinceOption) {
            try {
                $cutoff = Carbon::parse($sinceOption);
                $this->info("Only syncing events for issues updated since: " . $cutoff->toDateTimeString());
            } catch (\Exception $e) {
                $this->error("Invalid date format for --since: $sinceOption");

                return 1;
            }
        }

        $this->info("Starting sync of events for $repo...");

        $cursor = null;
        $hasNextPage = true;
        $page = 1;
        $totalIssues = null;
        $bar = null;

        while ($hasNextPage) {
            if ($maxPages !== null && $page > $maxPages) {
                $this->info("Reached maximum pages limit ($maxPages).");
                break;
            }

            try {
                // Fetch issues WITH events in a single query (eliminates N+1 problem)
                $response = $github->fetchIssuesWithEvents($owner, $name, $cursor);
                $nodes = $response['nodes'] ?? [];
                $cursor = $response['pageInfo']['endCursor'] ?? null;
                $hasNextPage = $response['pageInfo']['hasNextPage'] ?? false;

                // Initialize progress bar on first page
                if ($bar === null) {
                    $totalIssues = $response['totalCount'] ?? count($nodes);
                    $this->info("Fetching events for approximately $totalIssues issues...");
                    $bar = $this->output->createProgressBar($totalIssues);
                    $bar->start();
                }

                $documents = [];
                $reachedCutoff = false;

                foreach ($nodes as $issue) {
                    $issueNumber = $issue['number'];

                    // Extract events from inline data (no API call needed)
                    $events = $github->extractEventsFromIssue($issue);

                    // Check if we should stop based on most recent event in this issue
                    if ($cutoff && !empty($events)) {
                        // Get the most recent event date for this issue
                        $mostRecentEvent = collect($events)->sortByDesc('created_at')->first();
                        if ($mostRecentEvent) {
                            $mostRecentDate = Carbon::parse($mostRecentEvent['created_at']);
                            if ($mostRecentDate->lt($cutoff)) {
                                // This issue's events are all older than cutoff
                                // Since issues are sorted by updatedAt DESC, we can stop
                                $reachedCutoff = true;
                                break;
                            }
                        }
                    }

                    foreach ($events as $event) {
                        // Filter individual events by date
                        if ($cutoff) {
                            $eventDate = Carbon::parse($event['created_at']);
                            if ($eventDate->lt($cutoff)) {
                                continue;
                            }
                        }

                        $documents[] = [
                            'github_account_name' => $event['actor'],
                            'interaction_name' => $event['type'],
                            'issues-id' => $issueNumber,
                            'interaction_date' => Carbon::parse($event['created_at'])->toIso8601String(),
                        ];
                    }

                    $bar->advance();
                }

                if ($reachedCutoff) {
                    $this->info("\nReached cutoff date, stopping sync.");
                    break;
                }

                // Bulk index for better performance
                if (!empty($documents)) {
                    $openSearch->indexBulk(
                        OpenSearchService::getIndexWithPrefix('interactions'),
                        $documents
                    );
                }

                $page++;
            } catch (Throwable $e) {
                $this->warn("\nError syncing page $page: " . $e->getMessage());
                Log::error("Failed to process events page $page", ['exception' => $e]);
                break;
            }
        }

        if ($bar) {
            $bar->finish();
        }
        $this->info("\nDone syncing GitHub events.");

        return 0;
    }
}
