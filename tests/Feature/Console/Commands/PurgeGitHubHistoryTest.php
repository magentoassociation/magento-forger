<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use Mockery;
use Mockery\MockInterface;
use OpenSearch\Client;
use RuntimeException;
use Tests\TestCase;

class PurgeGitHubHistoryTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $deletes = [];

    /** @var array<string, mixed>|null */
    private ?array $searchParams = null;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('github.history_start', '2014-12-01');
        config()->set('opensearch.index_prefix', '');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testPurgesPreFloorItemsAndTheirRelatedDocumentsAcrossAllIndexes(): void
    {
        $this->bindClient([2, 3, 3]);

        $this->artisan('sync:github:purge-history', ['--force' => true])
            ->expectsOutputToContain('github-pull-requests: deleted 4 documents.')
            ->expectsOutputToContain('github-interactions: deleted 4 documents.')
            ->expectsOutputToContain('Run leaderboard:compute')
            ->assertExitCode(0);

        $this->assertSame(
            ['lt' => '2014-12-01T00:00:00+00:00'],
            $this->searchParams['body']['query']['range']['created_at'],
        );
        $this->assertSame('github-pull-requests,github-issues', $this->searchParams['index']);

        $byIndex = array_column($this->deletes, 'body', 'index');
        $this->assertSame(['ids' => ['values' => ['2', '3']]], $byIndex['github-pull-requests']['query']);
        $this->assertSame(['ids' => ['values' => ['2', '3']]], $byIndex['github-issues']['query']);
        $this->assertSame(['terms' => ['pr_number' => [2, 3]]], $byIndex['github-pr-reviews']['query']);
        $this->assertSame(['terms' => ['pr_number' => [2, 3]]], $byIndex['github-pr-timeline']['query']);
        $this->assertSame(['terms' => ['issues-id' => [2, 3]]], $byIndex['github-events']['query']);
        $this->assertSame(['terms' => ['issues-id' => [2, 3]]], $byIndex['github-interactions']['query']);
    }

    public function testNothingToPurgeDeletesNothing(): void
    {
        $this->bindClient([]);

        $this->artisan('sync:github:purge-history', ['--force' => true])
            ->expectsOutputToContain('Nothing to purge.')
            ->assertExitCode(0);

        $this->assertSame([], $this->deletes);
    }

    public function testDecliningConfirmationDeletesNothing(): void
    {
        $this->bindClient([2]);

        $this->artisan('sync:github:purge-history')
            ->expectsConfirmation('Delete 1 issues/PRs created before 2014-12-01 and all their related documents?', 'no')
            ->expectsOutputToContain('Operation canceled.')
            ->assertExitCode(0);

        $this->assertSame([], $this->deletes);
    }

    public function testFailsWhenHistoryStartIsUnset(): void
    {
        config()->set('github.history_start', null);

        $this->artisan('sync:github:purge-history', ['--force' => true])
            ->expectsOutputToContain('github.history_start is not set')
            ->assertExitCode(1);
    }

    public function testRefusesWhenPreFloorSetExceedsOnePage(): void
    {
        $this->bindClient([2], total: 10001);

        $this->artisan('sync:github:purge-history', ['--force' => true])
            ->expectsOutputToContain('Purge in batches.')
            ->assertExitCode(1);

        $this->assertSame([], $this->deletes);
    }

    public function testReportsFailureButKeepsPurgingOtherIndexes(): void
    {
        $this->bindClient([2], failIndex: 'github-events');

        $this->artisan('sync:github:purge-history', ['--force' => true])
            ->expectsOutputToContain('github-events: delete failed: boom')
            ->expectsOutputToContain('github-interactions: deleted 4 documents.')
            ->assertExitCode(1);
    }

    /**
     * @param  list<int>  $numbers  Issue/PR numbers the pre-floor search returns (duplicates allowed).
     * @param  int|null  $total  Reported total hits; defaults to count($numbers).
     * @param  string|null  $failIndex  Index whose delete throws.
     */
    private function bindClient(array $numbers, ?int $total = null, ?string $failIndex = null): void
    {
        $this->mock(Client::class, function (MockInterface $client) use ($numbers, $total, $failIndex) {
            $client->shouldReceive('search')->andReturnUsing(function (array $params) use ($numbers, $total) {
                $this->searchParams = $params;

                return ['hits' => [
                    'total' => ['value' => $total ?? count($numbers)],
                    'hits' => array_map(static fn (int $n): array => ['_id' => (string) $n], $numbers),
                ]];
            });
            $client->shouldReceive('deleteByQuery')->andReturnUsing(function (array $params) use ($failIndex) {
                if ($params['index'] === $failIndex) {
                    throw new RuntimeException('boom');
                }
                $this->deletes[] = $params;

                return ['deleted' => 4];
            });
        });
    }
}
