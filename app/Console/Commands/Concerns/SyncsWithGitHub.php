<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Exceptions\GitHubGraphQLException;
use App\Exceptions\InvalidSyncCutoffException;
use Carbon\Carbon;
use Closure;
use Throwable;

trait SyncsWithGitHub
{
    /**
     * Validates the github.repo config and splits it into owner/name.
     * Outputs an error and returns null when invalid.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function resolveRepository(): ?array
    {
        $parts = explode('/', (string) config('github.repo'));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            $this->error('Missing or invalid repository. Set it in config/github.php');

            return null;
        }

        return $parts;
    }

    /**
     * Parses the --since option into a Carbon cutoff.
     *
     * @return Carbon|null Carbon on success, null when --since was not given.
     *
     * @throws InvalidSyncCutoffException when --since is present but cannot be parsed.
     */
    protected function parseCutoff(string $filteringLabel): ?Carbon
    {
        $since = $this->option('since');

        if (! $since) {
            $this->info('No date filter applied');

            return null;
        }

        try {
            $cutoff = Carbon::parse($since);
        } catch (Throwable $e) {
            throw new InvalidSyncCutoffException("Invalid date format for --since option: $since", 0, $e);
        }

        $this->info($filteringLabel.': '.$cutoff->toDateTimeString());

        return $cutoff;
    }

    protected function reportCursorResume(?string $cursor): void
    {
        if ($cursor) {
            $this->info("Resuming from cursor: $cursor");
        }
    }

    protected function makeOnPageCallback(?int $totalPages): Closure
    {
        return function (int $page, ?string $cursor) use ($totalPages) {
            $this->info('Page '.$page.($totalPages ? ' of '.$totalPages : '').' done. Cursor: '.$cursor);
        };
    }

    protected function makeOnNodeCallback(): Closure
    {
        return fn ($node) => $this->line("#{$node['number']}: {$node['title']} ({$node['state']})");
    }

    /**
     * @param  bool  $errorOccurred  Passed by reference; set to true on first error.
     * @param  Closure|null  $log  Optional per-command log call; receives (Throwable $e, int $page).
     */
    protected function makeOnErrorCallback(bool &$errorOccurred, ?Closure $log = null): Closure
    {
        return function (Throwable $e, int $page) use (&$errorOccurred, $log) {
            $errorOccurred = true;
            $this->error("Page {$page} failed: ".$e->getMessage());

            if ($e instanceof GitHubGraphQLException) {
                foreach ($e->getContext()['errors'] ?? [] as $graphqlError) {
                    $this->error('GraphQL error: '.($graphqlError['message'] ?? json_encode($graphqlError)));
                }
            }

            if ($log !== null) {
                $log($e, $page);
            }
        };
    }

    protected function reportCutoffReached(array $result, ?Carbon $cutoff, string $subject): void
    {
        if ($result['cutoffReached'] && $cutoff !== null) {
            $this->info("Last {$subject} is older than given cutoff ({$cutoff->toDateTimeString()}), stopping sync.");
        }
    }

    protected function reportDone(bool $errorOccurred, string $doneMessage): void
    {
        if (! $errorOccurred) {
            $this->info($doneMessage);
        }
    }
}
