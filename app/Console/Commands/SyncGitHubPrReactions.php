<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\SyncsWithGitHub;
use App\Services\GitHub\GitHubPullRequestService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Support\Facades\Log;

/**
 * Keeps 👍 counts fresh on open PRs. Adding a reaction does not bump a PR's
 * updatedAt, so the incremental PR sync (which stops on updatedAt) never sees
 * new votes; this walks every open PR instead, fetching only the cheap fields.
 */
class SyncGitHubPrReactions extends Command implements Isolatable
{
    use SyncsWithGitHub;

    protected $signature = 'sync:github:pr-reactions
                            {--cursor= : Optional endCursor to resume pagination}';

    protected $description = 'Refresh thumbs-up counts and linked issues on open GitHub Pull Requests';

    public function handle(GitHubPullRequestService $github, OpenSearchService $openSearch, GitHubSyncer $syncer): int
    {
        if (($parts = $this->resolveRepository()) === null) {
            return 1;
        }

        [$owner, $name] = $parts;
        $cursor = $this->option('cursor');
        $this->reportCursorResume($cursor);

        $errorOccurred = false;

        $syncer->sync(
            fetchPage: fn ($c) => $github->fetchOpenPullRequestReactions($owner, $name, $c),
            index: fn (array $nodes) => $openSearch->updatePullRequestReactions($nodes),
            cursor: $cursor,
            onPage: $this->makeOnPageCallback(null),
            onError: $this->makeOnErrorCallback(
                $errorOccurred,
                fn ($e, $page) => Log::warning('GitHub PR reactions sync failed', ['exception' => $e]),
            ),
            createdSince: $this->historyStart(),
        );

        $this->reportDone($errorOccurred, 'Done syncing PR reactions.');

        return $errorOccurred ? 1 : 0;
    }
}
