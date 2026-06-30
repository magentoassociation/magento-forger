<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Exceptions\GitHubGraphQLException;
use App\Services\GitHub\GitHubIssueService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class SyncGitHubIssuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('github.repo', 'owner/repo');
        Log::spy();
    }

    public function testInvalidSinceReturnsErrorAndExitsWithCode1(): void
    {
        $this->artisan('sync:github:issues', ['--since' => 'not-a-date'])
            ->assertExitCode(1)
            ->expectsOutputToContain('Invalid date format for --since option: not-a-date');
    }

    public function testValidIsoDateSinceIsAccepted(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:issues', ['--since' => '2026-01-01'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Filtering issues updated since');
    }

    public function testValidRelativeSinceIsAccepted(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:issues', ['--since' => '1 hour ago'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Filtering issues updated since');
    }

    public function testNoSinceRunsFullSyncWithoutCutoffMessage(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:issues')
            ->assertExitCode(0)
            ->expectsOutputToContain('No date filter applied');
    }

    public function testMissingRepoConfigReturnsError(): void
    {
        config()->set('github.repo', null);

        $this->artisan('sync:github:issues')
            ->assertExitCode(1)
            ->expectsOutputToContain('Missing or invalid repository');
    }

    public function testInvalidRepoFormatReturnsError(): void
    {
        config()->set('github.repo', 'invalid-no-slash');

        $this->artisan('sync:github:issues')
            ->assertExitCode(1)
            ->expectsOutputToContain('Missing or invalid repository');
    }

    public function testSuccessfulSyncShowsDoneMessage(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:issues')
            ->assertExitCode(0)
            ->expectsOutputToContain('Done syncing issues.');
    }

    public function testPageErrorShowsErrorMessageAndSuppressesDoneMessage(): void
    {
        $this->mock(GitHubIssueService::class);
        $this->mock(OpenSearchService::class);
        $this->mock(GitHubSyncer::class)
            ->shouldReceive('sync')
            ->andReturnUsing(function (
                callable $fetchPage,
                callable $index,
                $cutoff,
                $cursor,
                $onPage,
                $onNode,
                $onError
            ) {
                $onError(new RuntimeException('Connection refused'), 3);

                return ['pages' => 3, 'cutoffReached' => false];
            });

        $this->artisan('sync:github:issues')
            ->assertExitCode(0)
            ->expectsOutputToContain('Page 3 failed: Connection refused')
            ->doesntExpectOutputToContain('Done syncing issues.');
    }

    public function testGraphqlErrorShowsIndividualErrors(): void
    {
        $this->mock(GitHubIssueService::class);
        $this->mock(OpenSearchService::class);
        $this->mock(GitHubSyncer::class)
            ->shouldReceive('sync')
            ->andReturnUsing(function (
                callable $fetchPage,
                callable $index,
                $cutoff,
                $cursor,
                $onPage,
                $onNode,
                $onError
            ) {
                $onError(new GitHubGraphQLException('GitHub GraphQL API error', [
                    'errors' => [['message' => 'Could not resolve to a Repository']],
                ]), 2);

                return ['pages' => 2, 'cutoffReached' => false];
            });

        $this->artisan('sync:github:issues')
            ->assertExitCode(0)
            ->expectsOutputToContain('Page 2 failed: GitHub GraphQL API error')
            ->expectsOutputToContain('GraphQL error: Could not resolve to a Repository');
    }

    private function mockSyncerReturnsEmpty(): void
    {
        $this->mock(GitHubIssueService::class);

        $this->mock(OpenSearchService::class);

        $this->mock(GitHubSyncer::class)
            ->shouldReceive('sync')
            ->andReturn(['pages' => 0, 'cutoffReached' => false]);
    }
}
