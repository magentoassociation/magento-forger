<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests;

use App\Services\GitHub\GitHubSyncer;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Page retries back off for real seconds; no test should wait on them.
        $this->app->instance(GitHubSyncer::class, new GitHubSyncer(sleep: static function (): void {}));
    }
}
