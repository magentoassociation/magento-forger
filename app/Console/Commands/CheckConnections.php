<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenSearch\Client;
use RuntimeException;
use Throwable;

/**
 * Prove the live .env can reach every backing service. Run last by forger:env and
 * forger:deploy, so a password rotated in the wrong order (or a typo in a secret)
 * fails the deploy loudly instead of leaving a silently broken site.
 */
class CheckConnections extends Command
{
    protected $signature = 'app:check-connections';

    protected $description = 'Check the database, Redis and OpenSearch connections with the current configuration';

    public function handle(Client $openSearch): int
    {
        $failed = false;

        foreach ($this->checks($openSearch) as $name => $check) {
            try {
                $check();
                $this->line("<fg=green>✓</> {$name}");
            } catch (Throwable $e) {
                $failed = true;
                $this->line("<fg=red>✗ {$name}: {$e->getMessage()}</>");
            }
        }

        if ($failed) {
            $this->error('One or more connections failed. Check the .env values against the services.');

            return self::FAILURE;
        }

        $this->info('All connections OK.');

        return self::SUCCESS;
    }

    /**
     * Only the Redis connections the cache and session stores actually use are checked.
     *
     * @return array<string, Closure(): void>
     */
    private function checks(Client $openSearch): array
    {
        $checks = ['Database' => static fn () => DB::select('select 1')];

        $redisConnections = array_unique(array_filter([
            config('cache.default') === 'redis' ? config('cache.stores.redis.connection', 'default') : null,
            config('session.driver') === 'redis' ? (config('session.connection') ?? 'default') : null,
        ]));

        foreach ($redisConnections as $connection) {
            $checks["Redis ({$connection})"] = static fn () => Redis::connection($connection)->ping();
        }

        $checks['OpenSearch'] = static function () use ($openSearch): void {
            if (! $openSearch->ping()) {
                throw new RuntimeException('ping returned false');
            }
        };

        return $checks;
    }
}
