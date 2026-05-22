<?php
/**
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

/**
 * Sync all GitHub interactions (comments, reactions, etc.)
 * from issues and PRs into the "interactions" OpenSearch index.
 */
class SyncGitHubInteractions extends Command
{
    protected $signature = 'sync:github:interactions
                            {--since= : Only import issues/PRs updated since this relative time (e.g. "2 weeks", "5 days")}';

    protected $description = 'Sync all GitHub interactions into OpenSearch';

    public function handle(GitHubService $github, OpenSearchService $openSearch): int
    {
        $repo = config('github.repo', 'magento/magento2');

        if (!str_contains($repo, '/')) {
            $this->error('Missing or invalid repository. Set it in config/github.php');
            return 1;
        }

        [$owner, $name] = explode('/', $repo);
        $sinceOption = $this->option('since');
        $cutoff = null;

        if ($sinceOption) {
            try {
                $cutoff = Carbon::parse($sinceOption);
                $this->info("Only syncing interactions updated since: " . $cutoff->toDateTimeString());
            } catch (\Exception $e) {
                $this->error("Invalid date format for --since: $sinceOption");
                return 1;
            }
        } else {
            $this->info("No date cutoff applied. All interactions will be synced.");
        }

        $this->info("Starting sync of interactions for $repo...");

        $page = 1;
        $cursor = null;
        $hasNextPage = true;
        $totalIssues = null;
        $bar = null;

        while ($hasNextPage) {
            try {
                // Fetch issues WITH interactions in a single query (eliminates N+1 problem)
                $response = $github->fetchIssuesWithInteractions($owner, $name, $cursor);
                $nodes = $response['nodes'] ?? [];
                $cursor = $response['pageInfo']['endCursor'] ?? null;
                $hasNextPage = $response['pageInfo']['hasNextPage'] ?? false;

                // Initialize progress bar on first page
                if ($bar === null) {
                    $totalIssues = $response['totalCount'] ?? count($nodes);
                    $this->info("Fetching interactions for approximately $totalIssues issues...");
                    $bar = $this->output->createProgressBar($totalIssues);
                    $bar->start();
                }

                $interactions = [];
                $reachedCutoff = false;

                foreach ($nodes as $issue) {
                    $updatedAt = Carbon::parse($issue['updatedAt']);

                    if ($cutoff && $updatedAt->lt($cutoff)) {
                        // Issues are sorted by updatedAt DESC, so all remaining will be older
                        $reachedCutoff = true;
                        break;
                    }

                    $issueId = $issue['number'];

                    // Extract interactions from inline data (no API call needed)
                    $issueInteractions = $github->extractInteractionsFromIssue($issue);

                    foreach ($issueInteractions as $interaction) {
                        $interactions[] = [
                            'github_account_name' => $interaction['author'] ?? 'unknown',
                            'interaction_name' => $interaction['type'],
                            'issues-id' => $issueId,
                            'interaction_date' => Carbon::parse($interaction['date'])->toIso8601String(),
                        ];
                    }

                    $bar->advance();
                }

                if ($reachedCutoff) {
                    $this->info("\nReached cutoff date, stopping sync.");
                    break;
                }

                if (!empty($interactions)) {
                    $openSearch->indexBulk(
                        OpenSearchService::getIndexWithPrefix('interactions'),
                        $interactions
                    );
                }

                $page++;
            } catch (Throwable $e) {
                $this->warn("\nError syncing page $page: " . $e->getMessage());
                Log::warning('GitHub interaction sync error', ['exception' => $e]);
                break;
            }
        }

        if ($bar) {
            $bar->finish();
        }
        $this->info("\nDone syncing interactions.");

        return 0;
    }
}
