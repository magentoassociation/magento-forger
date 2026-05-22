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
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync GitHub Issues using GraphQL
 *
 * @package App\Console\Commands
 */
class SyncGitHubIssues extends Command implements Isolatable
{
    protected $signature = 'sync:github:issues
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Optional date to filter PRs since this date (e.g 2 days, 1 week, 1 month)}';

    protected $description = 'Sync GitHub Issues using GraphQL';

    public function handle(GitHubService $github, OpenSearchService $openSearch): int
    {
        $repo = config('github.repo');
        $cursor = $this->option('cursor');
        $since = $this->option('since');
        $cutoff = null;

        if (!$repo || !str_contains($repo, '/')) {
            $this->error('Missing or invalid repository. Set it in config/github.php');

            return 1;
        }

        if ($since) {
            $cutoff = Carbon::parse($since);
            if (!$cutoff->isValid()) {
                $this->error("Invalid date format for --since option: $since");

                return 1;
            }
            $this->info('Filtering issues updated since: ' . $cutoff->toDateTimeString());
        } else {
            $this->info('No date filter applied');
        }

        [$owner, $name] = explode('/', $repo);

        $totalCount = null;
        try {
            $totalCounts = $github->fetchIssueCount($owner, $name);
            $summary = $totalCounts->summary();
            $totalCount = $totalCounts->total;
            $this->info("Syncing issues for $repo. ($summary)");
        } catch (Throwable $e) {
            $this->warn('Could not retrieve issue count');
            Log::warning('GitHub issue count failed', ['exception' => $e]);
        }
        $totalPages = $totalCount ? ceil($totalCount / 100) : null;

        if ($cursor) {
            $this->info("Resuming from cursor: $cursor");
        }

        $fetchFailed = false;
        $page = 1;
        do {
            $hasNextPage = false;
            try {
                $response = $github->fetchIssues($owner, $name, $cursor);
                $nodes = $response['nodes'] ?? [];

                foreach ($nodes as $issue) {
                    $this->line("#{$issue['number']}: {$issue['title']} ({$issue['state']})");
                }

                $openSearch->indexIssues($nodes);

                $cursor = $response['pageInfo']['endCursor'] ?? null;
                $hasNextPage = $response['pageInfo']['hasNextPage'] ?? false;

                $last = $nodes[array_key_last($nodes)] ?? null;
                if ($last && $cutoff) {
                    $lastUpdatedAt = Carbon::parse($last['updatedAt']);
                    if ($lastUpdatedAt->lessThan($cutoff)) {
                        $this->info(
                            "Last issue is older than given cutoff ({$cutoff->toDateTimeString()}), stopping sync."
                        );
                        break;
                    }
                }

                $this->info("Page $page" . ($totalPages ? " of $totalPages" : '') . " done. Cursor: $cursor");
                $page++;
            } catch (Throwable $e) {
                $this->warn("Could not fetch issues for page $page");
                Log::warning('GitHub issue fetch failed', ['exception' => $e]);
                $fetchFailed = true;
            }
        } while ($hasNextPage);
        $this->info('Done syncing issues.');

        return $fetchFailed ? 1 : 0;
    }
}
