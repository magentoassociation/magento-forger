<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use Carbon\Carbon;
use Throwable;

class GitHubSyncer
{
    /**
     * Run a paginated GitHub sync with optional cutoff, per-node callbacks, and error recovery.
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
     * @param  callable(Throwable $e, int $page): void|null  $onError  Called on exception; loop continues.
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
            $hasNextPage = false;

            try {
                $response = $fetchPage($cursor);
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

                $cursor = $response['pageInfo']['endCursor'] ?? null;
                $hasNextPage = $response['pageInfo']['hasNextPage'] ?? false;

                $last = $nodes !== [] ? $nodes[array_key_last($nodes)] : null;
                if ($last !== null && $cutoff !== null) {
                    if (Carbon::parse($last['updatedAt'])->lessThan($cutoff)) {
                        $cutoffReached = true;
                        $hasNextPage = false;
                    }
                }

                if ($onPage !== null) {
                    $onPage($page, $cursor);
                }

                $page++;
            } catch (Throwable $e) {
                if ($onError !== null) {
                    $onError($e, $page);
                }
            }
        }

        return ['pages' => $page - 1, 'cutoffReached' => $cutoffReached];
    }
}
