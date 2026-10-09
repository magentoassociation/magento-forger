<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Services\GitHub\GitHubPullRequestService;
use App\Services\Search\OpenSearchService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SyncGitHubPrReactionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('github.repo', 'owner/repo');
        config()->set('github.history_start', '2014-12-01');
        Log::spy();
    }

    public function testPagesThroughOpenPrsAndUpdatesEachPage(): void
    {
        $pages = [
            null => ['nodes' => [$this->node(2)], 'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'c1']],
            'c1' => ['nodes' => [$this->node(1)], 'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'c2']],
        ];
        $this->mock(GitHubPullRequestService::class)
            ->shouldReceive('fetchOpenPullRequestReactions')
            ->twice()
            ->andReturnUsing(fn (string $owner, string $repo, ?string $cursor): array => $pages[$cursor ?? '']);

        $updated = [];
        $this->mock(OpenSearchService::class)
            ->shouldReceive('updatePullRequestReactions')
            ->andReturnUsing(function (array $nodes) use (&$updated): void {
                $updated[] = array_column($nodes, 'number');
            });

        $this->artisan('sync:github:pr-reactions')
            ->assertExitCode(0)
            ->expectsOutputToContain('Page 2 done. Cursor: c2')
            ->expectsOutputToContain('Done syncing PR reactions.');

        $this->assertSame([[2], [1]], $updated);
    }

    public function testDropsPrsCreatedBeforeHistoryStart(): void
    {
        $this->mock(GitHubPullRequestService::class)
            ->shouldReceive('fetchOpenPullRequestReactions')
            ->andReturn(['nodes' => [$this->node(1, '2013-01-01T00:00:00Z'), $this->node(2)], 'pageInfo' => ['hasNextPage' => false]]);

        $updated = [];
        $this->mock(OpenSearchService::class)
            ->shouldReceive('updatePullRequestReactions')
            ->andReturnUsing(function (array $nodes) use (&$updated): void {
                $updated = array_column($nodes, 'number');
            });

        $this->artisan('sync:github:pr-reactions')->assertExitCode(0);

        $this->assertSame([2], $updated);
    }

    public function testResumesFromCursor(): void
    {
        $this->mock(GitHubPullRequestService::class)
            ->shouldReceive('fetchOpenPullRequestReactions')
            ->once()
            ->with('owner', 'repo', 'abc')
            ->andReturn(['nodes' => [], 'pageInfo' => ['hasNextPage' => false]]);
        $this->mock(OpenSearchService::class)->shouldReceive('updatePullRequestReactions');

        $this->artisan('sync:github:pr-reactions', ['--cursor' => 'abc'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Resuming from cursor: abc');
    }

    public function testPageErrorExitsWithCode1AndSuppressesDoneMessage(): void
    {
        $this->mock(GitHubPullRequestService::class)
            ->shouldReceive('fetchOpenPullRequestReactions')
            ->andThrow(new RuntimeException('Connection refused'));
        $this->mock(OpenSearchService::class);

        $this->artisan('sync:github:pr-reactions')
            ->assertExitCode(1)
            ->expectsOutputToContain('Page 1 failed: Connection refused')
            ->doesntExpectOutputToContain('Done syncing PR reactions.');
    }

    public function testFailedUpdateExitsWithCode1(): void
    {
        $this->mock(GitHubPullRequestService::class)
            ->shouldReceive('fetchOpenPullRequestReactions')
            ->andReturn(['nodes' => [$this->node(1)], 'pageInfo' => ['hasNextPage' => false]]);
        $this->mock(OpenSearchService::class)
            ->shouldReceive('updatePullRequestReactions')
            ->andThrow(new RuntimeException('1 PR reaction update(s) failed: #1: mapper_parsing_exception'));

        $this->artisan('sync:github:pr-reactions')
            ->assertExitCode(1)
            ->expectsOutputToContain('Page 1 failed: 1 PR reaction update(s) failed')
            ->doesntExpectOutputToContain('Done syncing PR reactions.');
    }

    public function testMissingRepoConfigReturnsError(): void
    {
        config()->set('github.repo', null);

        $this->artisan('sync:github:pr-reactions')
            ->assertExitCode(1)
            ->expectsOutputToContain('Missing or invalid repository');
    }

    /**
     * @return array<string, mixed>
     */
    private function node(int $number, string $createdAt = '2026-01-01T00:00:00Z'): array
    {
        return [
            'number' => $number,
            'createdAt' => $createdAt,
            'updatedAt' => '2026-02-01T00:00:00Z',
            'isDraft' => false,
            'reactions' => ['totalCount' => 3],
            'closingIssuesReferences' => ['nodes' => []],
        ];
    }
}
