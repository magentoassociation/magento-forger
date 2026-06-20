<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GitHub\GitHubPullRequestService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGitHubPRs extends Command implements Isolatable
{
    protected $signature = 'sync:github:prs
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Optional date to filter PRs since this date (e.g 2 days, 1 week, 1 month)}';

    protected $description = 'Sync GitHub Pull Requests using GraphQL';

    public function handle(GitHubPullRequestService $github, OpenSearchService $openSearch, GitHubSyncer $syncer): int
    {
        $repo = config('github.repo');

        if (! $repo || ! str_contains($repo, '/')) {
            $this->error('Missing or invalid repository. Set it in config/github.php');

            return 1;
        }

        $cursor = $this->option('cursor');
        $since = $this->option('since');
        $cutoff = null;

        if ($since) {
            try {
                $cutoff = Carbon::parse($since);
            } catch (Throwable) {
                $this->error("Invalid date format for --since option: $since");

                return 1;
            }
            $this->info('Filtering PRs updated since: '.$cutoff->toDateTimeString());
        } else {
            $this->info('No date filter applied');
        }

        [$owner, $name] = explode('/', $repo);

        $totalPages = null;
        if ($cutoff === null) {
            try {
                $totalCounts = $github->fetchPullRequestCount($owner, $name);
                $this->info("Syncing PRs for $repo. ({$totalCounts->summary()})");
                $totalPages = (int) ceil($totalCounts->total / 100);
            } catch (Throwable $e) {
                $this->warn('Could not retrieve pull request count');
                Log::warning('GitHub PR count failed', ['exception' => $e]);
            }
        }

        if ($cursor) {
            $this->info("Resuming from cursor: $cursor");
        }

        $result = $syncer->sync(
            fetchPage: fn ($c) => $github->fetchPullRequests($owner, $name, $c),
            index: fn ($nodes) => $openSearch->indexPullRequests($nodes),
            cutoff: $cutoff,
            cursor: $cursor,
            onPage: function (int $page, ?string $c) use ($totalPages) {
                $this->info('Page '.$page.($totalPages ? ' of '.$totalPages : '').' done. Cursor: '.$c);
            },
            onNode: fn ($pr) => $this->line("#{$pr['number']}: {$pr['title']} ({$pr['state']})"),
            onError: function (Throwable $e, int $page) {
                $this->warn('Could not retrieve pull requests');
                Log::warning('GitHub PR sync failed', ['exception' => $e]);
            },
        );

        if ($result['cutoffReached'] && $cutoff !== null) {
            $this->info('Last PR is older than given cutoff ('.$cutoff->toDateTimeString().'), stopping sync.');
        }

        $this->info('Done syncing PRs.');

        return 0;
    }
}
