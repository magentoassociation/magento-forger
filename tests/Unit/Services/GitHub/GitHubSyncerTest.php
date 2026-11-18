<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\GitHub;

use App\Services\GitHub\GitHubSyncer;
use Carbon\Carbon;
use RuntimeException;
use Tests\TestCase;

class GitHubSyncerTest extends TestCase
{
    private GitHubSyncer $syncer;

    /** @var list<int> Backoff delays the syncer asked for, in seconds. */
    private array $sleeps = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncer = new GitHubSyncer(sleep: function (int $seconds): void {
            $this->sleeps[] = $seconds;
        });
    }

    private function makePage(array $nodes, bool $hasNextPage, ?string $endCursor = null): array
    {
        return [
            'nodes' => $nodes,
            'pageInfo' => ['endCursor' => $endCursor, 'hasNextPage' => $hasNextPage],
        ];
    }

    private function makeNode(string $updatedAt): array
    {
        return ['number' => 1, 'updatedAt' => $updatedAt];
    }

    public function testSinglePageSyncIndexesNodesAndReturnsPageCount(): void
    {
        $indexed = [];
        $nodes = [$this->makeNode('2024-01-01'), $this->makeNode('2024-01-02')];

        $result = $this->syncer->sync(
            fetchPage: fn ($cursor) => $this->makePage($nodes, false),
            index: function ($n) use (&$indexed) {
                $indexed = $n;
            },
        );

        $this->assertSame($nodes, $indexed);
        $this->assertSame(1, $result['pages']);
        $this->assertFalse($result['cutoffReached']);
    }

    public function testMultiPageSyncFollowsCursorUntilNoNextPage(): void
    {
        $pages = [
            $this->makePage([$this->makeNode('2024-03-01')], true, 'cursor-1'),
            $this->makePage([$this->makeNode('2024-02-01')], true, 'cursor-2'),
            $this->makePage([$this->makeNode('2024-01-01')], false, null),
        ];

        $cursorsUsed = [];
        $indexCalls = 0;
        $pageIndex = 0;

        $result = $this->syncer->sync(
            fetchPage: function (?string $cursor) use (&$pages, &$cursorsUsed, &$pageIndex) {
                $cursorsUsed[] = $cursor;

                return $pages[$pageIndex++];
            },
            index: function () use (&$indexCalls) {
                $indexCalls++;
            },
        );

        $this->assertSame([null, 'cursor-1', 'cursor-2'], $cursorsUsed);
        $this->assertSame(3, $indexCalls);
        $this->assertSame(3, $result['pages']);
        $this->assertFalse($result['cutoffReached']);
    }

    public function testStopsWhenLastNodeIsOlderThanCutoff(): void
    {
        $cutoff = Carbon::parse('2024-06-01');
        $pages = [
            $this->makePage([$this->makeNode('2024-08-01')], true, 'cursor-1'),
            $this->makePage([$this->makeNode('2024-04-01')], true, 'cursor-2'),
        ];

        $pageIndex = 0;
        $indexCalls = 0;

        $result = $this->syncer->sync(
            fetchPage: function ($cursor) use (&$pages, &$pageIndex) {
                return $pages[$pageIndex++];
            },
            index: function () use (&$indexCalls) {
                $indexCalls++;
            },
            cutoff: $cutoff,
        );

        $this->assertSame(2, $indexCalls);
        $this->assertSame(2, $result['pages']);
        $this->assertTrue($result['cutoffReached']);
    }

    public function testDoesNotStopWhenLastNodeIsNewerThanCutoff(): void
    {
        $cutoff = Carbon::parse('2024-01-01');
        $pages = [
            $this->makePage([$this->makeNode('2024-06-01')], false),
        ];

        $pageIndex = 0;

        $result = $this->syncer->sync(
            fetchPage: function ($cursor) use (&$pages, &$pageIndex) {
                return $pages[$pageIndex++];
            },
            index: fn ($n) => null,
            cutoff: $cutoff,
        );

        $this->assertFalse($result['cutoffReached']);
    }

    public function testOnNodeCallbackCalledForEachNode(): void
    {
        $nodes = [$this->makeNode('2024-01-01'), $this->makeNode('2024-01-02')];
        $seen = [];

        $this->syncer->sync(
            fetchPage: fn ($cursor) => $this->makePage($nodes, false),
            index: fn ($n) => null,
            onNode: function (array $node) use (&$seen) {
                $seen[] = $node;
            },
        );

        $this->assertSame($nodes, $seen);
    }

    public function testOnPageCallbackCalledWithPageNumberAndCursor(): void
    {
        $pages = [
            $this->makePage([$this->makeNode('2024-03-01')], true, 'c1'),
            $this->makePage([$this->makeNode('2024-01-01')], false, null),
        ];

        $pageIndex = 0;
        $pageCallbacks = [];

        $this->syncer->sync(
            fetchPage: function ($cursor) use (&$pages, &$pageIndex) {
                return $pages[$pageIndex++];
            },
            index: fn ($n) => null,
            onPage: function (int $page, ?string $cursor) use (&$pageCallbacks) {
                $pageCallbacks[] = [$page, $cursor];
            },
        );

        $this->assertSame([[1, 'c1'], [2, null]], $pageCallbacks);
    }

    public function testPageFailingEveryAttemptCallsOnErrorOnceAndStops(): void
    {
        $fetches = [];
        $errors = [];
        $indexCalls = 0;

        $result = $this->syncer->sync(
            fetchPage: function (?string $cursor) use (&$fetches) {
                $fetches[] = $cursor;
                if ($cursor === 'c1') {
                    throw new RuntimeException('fetch failed');
                }

                return $this->makePage([$this->makeNode('2024-03-01')], true, 'c1');
            },
            index: function () use (&$indexCalls) {
                $indexCalls++;
            },
            onError: function (\Throwable $e, int $page) use (&$errors) {
                $errors[] = [$e->getMessage(), $page];
            },
        );

        $this->assertSame([null, 'c1', 'c1', 'c1'], $fetches);
        $this->assertSame([30, 60], $this->sleeps);
        $this->assertSame([['fetch failed', 2]], $errors);
        $this->assertSame(1, $indexCalls);
        $this->assertSame(1, $result['pages']);
    }

    public function testTransientPageFailureIsRetriedAndSyncContinues(): void
    {
        $responses = [
            $this->makePage([$this->makeNode('2024-03-01')], true, 'c1'),
            new RuntimeException('502 Bad Gateway'),
            $this->makePage([$this->makeNode('2024-02-01')], true, 'c2'),
            $this->makePage([$this->makeNode('2024-01-01')], false),
        ];
        $indexCalls = 0;
        $errors = 0;

        $result = $this->syncer->sync(
            fetchPage: function () use (&$responses) {
                $next = array_shift($responses);
                if ($next instanceof \Throwable) {
                    throw $next;
                }

                return $next;
            },
            index: function () use (&$indexCalls) {
                $indexCalls++;
            },
            onError: function () use (&$errors) {
                $errors++;
            },
        );

        $this->assertSame(0, $errors);
        $this->assertSame([30], $this->sleeps);
        $this->assertSame(3, $indexCalls);
        $this->assertSame(3, $result['pages']);
    }

    public function testFailedIndexWriteIsRetriedWithTheSamePage(): void
    {
        $fetches = [];
        $indexAttempts = 0;

        $result = $this->syncer->sync(
            fetchPage: function (?string $cursor) use (&$fetches) {
                $fetches[] = $cursor;

                return $this->makePage([$this->makeNode('2024-01-01')], false);
            },
            index: function () use (&$indexAttempts) {
                if (++$indexAttempts === 1) {
                    throw new RuntimeException('OpenSearch unavailable');
                }
            },
        );

        $this->assertSame([null, null], $fetches);
        $this->assertSame(2, $indexAttempts);
        $this->assertSame(1, $result['pages']);
    }

    public function testResumesFromProvidedCursor(): void
    {
        $firstCursor = null;

        $this->syncer->sync(
            fetchPage: function (?string $cursor) use (&$firstCursor) {
                $firstCursor = $cursor;

                return $this->makePage([$this->makeNode('2024-01-01')], false);
            },
            index: fn ($n) => null,
            cursor: 'resume-cursor',
        );

        $this->assertSame('resume-cursor', $firstCursor);
    }

    public function testCreatedSinceDropsOlderNodesBeforeIndexingAndOnNode(): void
    {
        $old = ['number' => 1, 'createdAt' => '2013-05-01T00:00:00Z', 'updatedAt' => '2024-01-02'];
        $boundary = ['number' => 2, 'createdAt' => '2014-12-01T00:00:00Z', 'updatedAt' => '2024-01-01'];
        $new = ['number' => 3, 'createdAt' => '2016-01-01T00:00:00Z', 'updatedAt' => '2023-12-31'];
        $indexed = null;
        $seen = [];

        $this->syncer->sync(
            fetchPage: fn ($cursor) => $this->makePage([$old, $boundary, $new], false),
            index: function ($n) use (&$indexed) {
                $indexed = $n;
            },
            onNode: function (array $node) use (&$seen) {
                $seen[] = $node['number'];
            },
            createdSince: Carbon::parse('2014-12-01'),
        );

        $this->assertSame([$boundary, $new], $indexed);
        $this->assertSame([2, 3], $seen);
    }

    public function testCreatedSinceKeepsPagingWhenWholePageIsOlder(): void
    {
        // Pages are ordered by updatedAt, so an all-old page says nothing about the next one.
        $pages = [
            $this->makePage([['number' => 1, 'createdAt' => '2012-01-01', 'updatedAt' => '2024-02-01']], true, 'c1'),
            $this->makePage([['number' => 2, 'createdAt' => '2020-01-01', 'updatedAt' => '2024-01-01']], false),
        ];
        $pageIndex = 0;
        $indexed = [];

        $result = $this->syncer->sync(
            fetchPage: function ($cursor) use (&$pages, &$pageIndex) {
                return $pages[$pageIndex++];
            },
            index: function ($n) use (&$indexed) {
                $indexed[] = array_column($n, 'number');
            },
            createdSince: Carbon::parse('2014-12-01'),
        );

        $this->assertSame([[], [2]], $indexed);
        $this->assertSame(2, $result['pages']);
    }

    public function testCutoffUsesLastFetchedNodeEvenWhenCreatedSinceDropsIt(): void
    {
        $result = $this->syncer->sync(
            fetchPage: fn ($cursor) => $this->makePage(
                [['number' => 1, 'createdAt' => '2012-01-01', 'updatedAt' => '2024-01-01']],
                true,
                'c1',
            ),
            index: fn ($n) => null,
            cutoff: Carbon::parse('2024-06-01'),
            createdSince: Carbon::parse('2014-12-01'),
        );

        $this->assertTrue($result['cutoffReached']);
        $this->assertSame(1, $result['pages']);
    }

    public function testEmptyPageDoesNotTriggerCutoff(): void
    {
        $cutoff = Carbon::parse('2024-06-01');

        $result = $this->syncer->sync(
            fetchPage: fn ($cursor) => $this->makePage([], false),
            index: fn ($n) => null,
            cutoff: $cutoff,
        );

        $this->assertFalse($result['cutoffReached']);
    }
}
