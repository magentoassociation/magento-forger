<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Services\GitHub\GitHubInteractionService;
use App\Services\GitHub\GitHubIssueService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Tests\TestCase;

class SyncGitHubEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('github.repo', 'owner/repo');
    }

    public function test_invalid_since_returns_error_and_exits_with_code_1(): void
    {
        $this->artisan('sync:github:events', ['--since' => 'not-a-date'])
            ->assertExitCode(1)
            ->expectsOutputToContain('Invalid date format for --since option: not-a-date');
    }

    public function test_valid_iso_date_since_is_accepted(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:events', ['--since' => '2026-01-01'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Filtering events for issues updated since');
    }

    public function test_valid_relative_since_is_accepted(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:events', ['--since' => '1 hour ago'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Filtering events for issues updated since');
    }

    public function test_no_since_runs_full_sync(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:events')
            ->assertExitCode(0)
            ->expectsOutputToContain('No date filter applied');
    }

    public function test_missing_repo_config_returns_error(): void
    {
        config()->set('github.repo', null);

        $this->artisan('sync:github:events')
            ->assertExitCode(1)
            ->expectsOutputToContain('Missing or invalid repository');
    }

    public function test_invalid_repo_format_returns_error(): void
    {
        config()->set('github.repo', 'invalid-no-slash');

        $this->artisan('sync:github:events')
            ->assertExitCode(1)
            ->expectsOutputToContain('Missing or invalid repository');
    }

    public function test_cursor_option_resumes_from_cursor(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:events', ['--cursor' => 'Y3Vyc29yOnYyOpKt'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Resuming from cursor: Y3Vyc29yOnYyOpKt');
    }

    public function test_max_pages_option_is_accepted(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:events', ['--max-pages' => '5'])
            ->assertExitCode(0);
    }

    public function test_cutoff_reached_shows_stopping_message(): void
    {
        $this->mock(GitHubInteractionService::class);
        $this->mock(GitHubIssueService::class);
        $this->mock(OpenSearchService::class);
        $this->mock(GitHubSyncer::class)
            ->shouldReceive('sync')
            ->andReturn(['pages' => 3, 'cutoffReached' => true]);

        $this->artisan('sync:github:events', ['--since' => '1 week ago'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Last issue is older than given cutoff');
    }

    private function mockSyncerReturnsEmpty(): void
    {
        $this->mock(GitHubInteractionService::class);
        $this->mock(GitHubIssueService::class);
        $this->mock(OpenSearchService::class);
        $this->mock(GitHubSyncer::class)
            ->shouldReceive('sync')
            ->andReturn(['pages' => 0, 'cutoffReached' => false]);
    }
}
