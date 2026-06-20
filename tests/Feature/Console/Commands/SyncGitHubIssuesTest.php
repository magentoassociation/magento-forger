<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Services\GitHub\GitHubIssueService;
use App\Services\GitHub\GitHubSyncer;
use App\Services\Search\OpenSearchService;
use Tests\TestCase;

class SyncGitHubIssuesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('github.repo', 'owner/repo');
    }

    public function test_invalid_since_returns_error_and_exits_with_code_1(): void
    {
        $this->artisan('sync:github:issues', ['--since' => 'not-a-date'])
            ->assertExitCode(1)
            ->expectsOutputToContain('Invalid date format for --since option: not-a-date');
    }

    public function test_valid_iso_date_since_is_accepted(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:issues', ['--since' => '2026-01-01'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Filtering issues updated since');
    }

    public function test_valid_relative_since_is_accepted(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:issues', ['--since' => '1 hour ago'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Filtering issues updated since');
    }

    public function test_no_since_runs_full_sync_without_cutoff_message(): void
    {
        $this->mockSyncerReturnsEmpty();

        $this->artisan('sync:github:issues')
            ->assertExitCode(0)
            ->expectsOutputToContain('No date filter applied');
    }

    public function test_missing_repo_config_returns_error(): void
    {
        config()->set('github.repo', null);

        $this->artisan('sync:github:issues')
            ->assertExitCode(1)
            ->expectsOutputToContain('Missing or invalid repository');
    }

    public function test_invalid_repo_format_returns_error(): void
    {
        config()->set('github.repo', 'invalid-no-slash');

        $this->artisan('sync:github:issues')
            ->assertExitCode(1)
            ->expectsOutputToContain('Missing or invalid repository');
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
