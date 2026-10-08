<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Services\GitHub\GitHubSyncer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SyncHistoryStartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('github.repo', 'owner/repo');
        Log::spy();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function commands(): array
    {
        return [
            'prs' => ['sync:github:prs'],
            'issues' => ['sync:github:issues'],
            'events' => ['sync:github:events'],
            'interactions' => ['sync:github:interactions'],
        ];
    }

    #[DataProvider('commands')]
    public function testSyncPassesConfiguredHistoryStartAsCreatedSince(string $command): void
    {
        config()->set('github.history_start', '2014-12-01');
        $passed = false;

        $this->mock(GitHubSyncer::class)
            ->shouldReceive('sync')
            ->once()
            ->andReturnUsing(function (...$args) use (&$passed) {
                $passed = $args[7] ?? null; // createdSince: Mockery passes named args positionally

                return ['pages' => 0, 'cutoffReached' => false];
            });

        // --since skips the total-count API call.
        $this->artisan($command, ['--since' => '2026-01-01'])->assertExitCode(0);

        $this->assertInstanceOf(Carbon::class, $passed);
        $this->assertSame('2014-12-01', $passed->toDateString());
    }

    #[DataProvider('commands')]
    public function testSyncPassesNoCreatedSinceWhenHistoryStartIsUnset(string $command): void
    {
        config()->set('github.history_start', null);
        $passed = false;

        $this->mock(GitHubSyncer::class)
            ->shouldReceive('sync')
            ->once()
            ->andReturnUsing(function (...$args) use (&$passed) {
                $passed = $args[7] ?? null; // createdSince: Mockery passes named args positionally

                return ['pages' => 0, 'cutoffReached' => false];
            });

        $this->artisan($command, ['--since' => '2026-01-01'])->assertExitCode(0);

        $this->assertNull($passed);
    }
}
