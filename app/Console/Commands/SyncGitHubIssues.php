<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SyncsWithGitHub;
use App\Services\GitHub\GitHubIssueService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGitHubIssues extends Command implements Isolatable
{
    use SyncsWithGitHub;

    protected $signature = 'sync:github:issues
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Optional date to filter issues since this date (e.g 2 days, 1 week, 1 month)}';

    protected $description = 'Sync GitHub Issues using GraphQL';

    public function handle(GitHubIssueService $github, OpenSearchService $openSearch, GitHubSyncer $syncer): int
    {
        if (($parts = $this->resolveRepository()) === null) {
            return 1;
        }

        [$owner, $name] = $parts;
        $cursor = $this->option('cursor');
        $cutoff = $this->parseCutoff('Filtering issues updated since');

        if ($cutoff === false) {
            return 1;
        }

        $totalPages = null;

        if ($cutoff === null) {
            try {
                $totalCounts = $github->fetchIssueCount($owner, $name);
                $this->info("Syncing issues for {$owner}/{$name}. ({$totalCounts->summary()})");
                $totalPages = (int) ceil($totalCounts->total / 100);
            } catch (Throwable $e) {
                $this->warn('Could not retrieve issue count');
                Log::warning('GitHub issue count failed', ['exception' => $e]);
            }
        }

        $this->reportCursorResume($cursor);

        $errorOccurred = false;

        $result = $syncer->sync(
            fetchPage: fn ($c) => $github->fetchIssues($owner, $name, $c),
            index: fn ($nodes) => $openSearch->indexIssues($nodes),
            cutoff: $cutoff,
            cursor: $cursor,
            onPage: $this->makeOnPageCallback($totalPages),
            onNode: $this->makeOnNodeCallback(),
            onError: $this->makeOnErrorCallback(
                $errorOccurred,
                fn ($e, $page) => Log::warning('GitHub issue fetch failed', ['exception' => $e]),
            ),
        );

        $this->reportCutoffReached($result, $cutoff, 'issue');
        $this->reportDone($errorOccurred, 'Done syncing issues.');

        return 0;
    }
}
