<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GitHub\GitHubIssueService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGitHubIssues extends Command implements Isolatable
{
    protected $signature = 'sync:github:issues
                            {--cursor= : Optional endCursor to resume pagination}
                            {--since= : Optional date to filter issues since this date (e.g 2 days, 1 week, 1 month)}';

    protected $description = 'Sync GitHub Issues using GraphQL';

    public function handle(GitHubIssueService $github, OpenSearchService $openSearch, GitHubSyncer $syncer): int
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
            $cutoff = Carbon::tryParse($since);
            if ($cutoff === null) {
                $this->error("Invalid date format for --since option: $since");

                return 1;
            }
            $this->info('Filtering issues updated since: '.$cutoff->toDateTimeString());
        } else {
            $this->info('No date filter applied');
        }

        [$owner, $name] = explode('/', $repo);

        $totalPages = null;
        try {
            $totalCounts = $github->fetchIssueCount($owner, $name);
            $this->info("Syncing issues for $repo. ({$totalCounts->summary()})");
            $totalPages = (int) ceil($totalCounts->total / 100);
        } catch (Throwable $e) {
            $this->warn('Could not retrieve issue count');
            Log::warning('GitHub issue count failed', ['exception' => $e]);
        }

        if ($cursor) {
            $this->info("Resuming from cursor: $cursor");
        }

        $result = $syncer->sync(
            fetchPage: fn ($c) => $github->fetchIssues($owner, $name, $c),
            index: fn ($nodes) => $openSearch->indexIssues($nodes),
            cutoff: $cutoff,
            cursor: $cursor,
            onPage: function (int $page, ?string $c) use ($totalPages) {
                $this->info('Page '.$page.($totalPages ? ' of '.$totalPages : '').' done. Cursor: '.$c);
            },
            onNode: fn ($issue) => $this->line("#{$issue['number']}: {$issue['title']} ({$issue['state']})"),
            onError: function (Throwable $e, int $page) {
                $this->warn("Could not fetch issues for page $page");
                Log::warning('GitHub issue fetch failed', ['exception' => $e]);
            },
        );

        if ($result['cutoffReached'] && $cutoff !== null) {
            $this->info('Last issue is older than given cutoff ('.$cutoff->toDateTimeString().'), stopping sync.');
        }

        $this->info('Done syncing issues.');

        return 0;
    }
}
