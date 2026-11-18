<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class GitHubSyncer
{
    /**
     * @param  int  $maxPageAttempts  Tries per page before giving up on the sync.
     * @param  int  $retryBaseSeconds  Wait before the first retry; doubles on each one after.
     * @param  Closure(int $seconds): void|null  $sleep  Override for tests.
     */
    public function __construct(
        private readonly int $maxPageAttempts = 3,
        private readonly int $retryBaseSeconds = 30,
        private readonly ?Closure $sleep = null,
    ) {}

    /**
     * Run a paginated GitHub sync with optional cutoff, per-node callbacks, and page retries.
     *
     * A page that throws is retried with backoff (by then the HTTP client's own retries have
     * already failed, so this rides out longer outages and secondary rate limits). Once its
     * attempts run out, onError is called and the sync stops: without that page's endCursor
     * there is no next page to fetch.
     *
     * @param  callable(?string $cursor): array{
     *     nodes: array,
     *     pageInfo: array{endCursor: ?string, hasNextPage: bool}
     * }  $fetchPage
     * @param  callable(array $nodes): void  $index
     * @param  Carbon|null  $cutoff  Stop when the last node's updatedAt is older than this.
     * @param  string|null  $cursor  Resume from this cursor.
     * @param  callable(int $page, ?string $cursor): void|null  $onPage  Called after each page is indexed.
     * @param  callable(array $node): void|null  $onNode  Called for each kept node before indexing.
     * @param  callable(Throwable $e, int $page): void|null  $onError  Called when a page fails every attempt; the sync then stops.
     * @param  Carbon|null  $createdSince  Drop nodes whose createdAt is older than this. Pages are
     *                                     ordered by updatedAt, so this filters rather than stops.
     * @return array{pages: int, cutoffReached: bool}
     */
    public function sync(
        callable $fetchPage,
        callable $index,
        ?Carbon $cutoff = null,
        ?string $cursor = null,
        ?callable $onPage = null,
        ?callable $onNode = null,
        ?callable $onError = null,
        ?Carbon $createdSince = null,
    ): array {
        $page = 1;
        $cutoffReached = false;
        $hasNextPage = true;

        while ($hasNextPage) {
            try {
                $response = $this->fetchWithRetry($fetchPage, $cursor, $page, function (array $response) use ($index, $onNode, $createdSince): void {
                    $nodes = $response['nodes'] ?? [];
                    $kept = $createdSince === null ? $nodes : array_values(array_filter(
                        $nodes,
                        static fn (array $node): bool => Carbon::parse($node['createdAt'])->gte($createdSince),
                    ));

                    if ($onNode !== null) {
                        foreach ($kept as $node) {
                            $onNode($node);
                        }
                    }

                    $index($kept);
                });
            } catch (Throwable $e) {
                if ($onError !== null) {
                    $onError($e, $page);
                }

                break;
            }

            $nodes = $response['nodes'] ?? [];
            $cursor = $response['pageInfo']['endCursor'] ?? null;
            $hasNextPage = $response['pageInfo']['hasNextPage'] ?? false;

            $last = $nodes !== [] ? $nodes[array_key_last($nodes)] : null;
            if ($last !== null && $cutoff !== null && Carbon::parse($last['updatedAt'])->lessThan($cutoff)) {
                $cutoffReached = true;
                $hasNextPage = false;
            }

            if ($onPage !== null) {
                $onPage($page, $cursor);
            }

            $page++;
        }

        return ['pages' => $page - 1, 'cutoffReached' => $cutoffReached];
    }

    /**
     * Fetch a page and index it, retrying both with exponential backoff.
     *
     * @param  callable(array $response): void  $indexPage
     * @return array<string, mixed> The page response.
     *
     * @throws Throwable The last attempt's exception once every attempt has failed.
     */
    private function fetchWithRetry(callable $fetchPage, ?string $cursor, int $page, callable $indexPage): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $fetchPage($cursor);
                $indexPage($response);

                return $response;
            } catch (Throwable $e) {
                if ($attempt >= $this->maxPageAttempts) {
                    throw $e;
                }

                $delay = $this->retryBaseSeconds * (2 ** ($attempt - 1));
                Log::warning("GitHub sync page {$page} failed (attempt {$attempt}), retrying in {$delay}s", ['exception' => $e]);
                ($this->sleep ?? static fn (int $seconds) => sleep($seconds))($delay);
            }
        }
    }
}
