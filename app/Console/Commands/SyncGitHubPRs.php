<?php
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
 * Sync GitHub Pull Requests using GraphQL
 *
 * @package App\Console\Commands
 */
class SyncGitHubPRs extends Command implements Isolatable
{
    protected $signature = 'sync:github:prs
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Optional date to filter PRs since this date (e.g 2 days, 1 week, 1 month)}';

    protected $description = 'Sync GitHub Pull Requests using GraphQL';

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
            $this->info('Filtering PRs updated since: ' . $cutoff->toDateTimeString());
        } else {
            $this->info('No date filter applied');
        }

        [$owner, $name] = explode('/', $repo);

        $totalCount = null;
        try {
            $totalCounts = $github->fetchPullRequestCount($owner, $name);
            $summary = $totalCounts->summary();
            $totalCount = $totalCounts->total;
            $this->info("Syncing PRs for $repo. ($summary)");
        } catch (Throwable $e) {
            $this->warn('Could not retrieve pull request count');
            Log::warning('GitHub PR count failed', ['exception' => $e]);
        }
        $totalPages = $totalCount ? ceil($totalCount / 100) : null;

        if ($cursor) {
            $this->info("Resuming from cursor: $cursor");
        }
        $page = 1;
        do {
            $hasNextPage = false;
            try {
                $response = $github->fetchPullRequests($owner, $name, $cursor);
                $nodes = $response['nodes'] ?? [];

                foreach ($nodes as $pr) {
                    $this->line("#{$pr['number']}: {$pr['title']} ({$pr['state']})");
                }

                $openSearch->indexPullRequests($nodes);

                $cursor = $response['pageInfo']['endCursor'] ?? null;
                $hasNextPage = $response['pageInfo']['hasNextPage'] ?? false;

                $last = $nodes[array_key_last($nodes)] ?? null;
                if ($last && $cutoff) {
                    $lastUpdatedAt = Carbon::parse($last['updatedAt']);
                    if ($lastUpdatedAt->lessThan($cutoff)) {
                        $this->info("Last PR is older than given cutoff ({$cutoff->toDateTimeString()}), stopping sync.");
                        break;
                    }
                }

                $this->info("Page $page" . ($totalPages ? " of $totalPages" : '') . " done. Cursor: $cursor");
                $page++;
            } catch (Throwable $e) {
                $this->warn('Could not retrieve pull requests');
                Log::warning('GitHub PR sync failed', ['exception' => $e]);
            }

        } while ($hasNextPage);

        $this->info('Done syncing PRs.');
        return 0;
    }
}
