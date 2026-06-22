<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SyncsWithGitHub;
use App\Exceptions\InvalidSyncCutoffException;
use App\Services\GitHub\GitHubPullRequestService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGitHubPRs extends Command implements Isolatable
{
    use SyncsWithGitHub;

    protected $signature = 'sync:github:prs
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Optional date to filter PRs since this date (e.g 2 days, 1 week, 1 month)}';

    protected $description = 'Sync GitHub Pull Requests using GraphQL';

    public function handle(GitHubPullRequestService $github, OpenSearchService $openSearch, GitHubSyncer $syncer): int
    {
        if (($parts = $this->resolveRepository()) === null) {
            return 1;
        }

        [$owner, $name] = $parts;
        $cursor = $this->option('cursor');
        try {
            $cutoff = $this->parseCutoff('Filtering PRs updated since');
        } catch (InvalidSyncCutoffException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $totalPages = null;

        if ($cutoff === null) {
            try {
                $totalCounts = $github->fetchPullRequestCount($owner, $name);
                $this->info("Syncing PRs for {$owner}/{$name}. ({$totalCounts->summary()})");
                $totalPages = (int) ceil($totalCounts->total / 10);
            } catch (Throwable $e) {
                $this->warn('Could not retrieve pull request count');
                Log::warning('GitHub PR count failed', ['exception' => $e]);
            }
        }

        $this->reportCursorResume($cursor);

        $errorOccurred = false;

        $result = $syncer->sync(
            fetchPage: fn ($c) => $github->fetchPullRequests($owner, $name, $c),
            index: function (array $nodes) use ($github, $openSearch, $owner, $name) {
                $expanded = array_map(fn ($pr) => $github->expandTimelineItems($pr, $owner, $name), $nodes);
                $openSearch->indexPullRequests($expanded);
            },
            cutoff: $cutoff,
            cursor: $cursor,
            onPage: $this->makeOnPageCallback($totalPages),
            onNode: $this->makeOnNodeCallback(),
            onError: $this->makeOnErrorCallback(
                $errorOccurred,
                fn ($e, $page) => Log::warning('GitHub PR sync failed', ['exception' => $e]),
            ),
        );

        $this->reportCutoffReached($result, $cutoff, 'PR');
        $this->reportDone($errorOccurred, 'Done syncing PRs.');

        return 0;
    }
}
