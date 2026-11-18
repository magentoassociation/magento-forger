<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Redis;
use Mockery;
use OpenSearch\Client;
use RuntimeException;
use Tests\TestCase;

class CheckConnectionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array']);
    }

    public function testPassesWhenEveryConnectionWorks(): void
    {
        $this->bindOpenSearch(true);

        $this->artisan('app:check-connections')
            ->expectsOutputToContain('✓ Database')
            ->expectsOutputToContain('✓ OpenSearch')
            ->expectsOutput('All connections OK.')
            ->assertSuccessful();
    }

    public function testFailsWhenOpenSearchIsUnreachable(): void
    {
        $this->bindOpenSearch(false);

        $this->artisan('app:check-connections')
            ->expectsOutputToContain('✗ OpenSearch: ping returned false')
            ->assertFailed();
    }

    public function testChecksOnlyTheRedisConnectionsTheStoresUse(): void
    {
        config([
            'cache.default' => 'redis',
            'cache.stores.redis.connection' => 'cache',
            'session.driver' => 'redis',
            'session.connection' => 'session',
        ]);
        $this->bindOpenSearch(true);

        $cache = Mockery::mock();
        $cache->shouldReceive('ping')->once()->andReturn(true);
        $session = Mockery::mock();
        $session->shouldReceive('ping')->once()->andThrow(new RuntimeException('WRONGPASS invalid username-password pair'));
        Redis::shouldReceive('connection')->with('cache')->once()->andReturn($cache);
        Redis::shouldReceive('connection')->with('session')->once()->andReturn($session);

        $this->artisan('app:check-connections')
            ->expectsOutputToContain('✓ Redis (cache)')
            ->expectsOutputToContain('✗ Redis (session): WRONGPASS invalid username-password pair')
            ->assertFailed();
    }

    private function bindOpenSearch(bool $reachable): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('ping')->andReturn($reachable);
        $this->app->instance(Client::class, $client);
    }
}
