<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Label;

use App\Services\Label\LabelOrchestrator;
use RuntimeException;
use Tests\TestCase;

class LabelOrchestratorTest extends TestCase
{
    public function test_resolve_repo_throws_when_configuration_is_missing(): void
    {
        config(['github.repo' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GitHub repository is not configured');

        (new LabelOrchestrator)->resolveRepo();
    }

    public function test_resolve_repo_throws_when_configuration_has_too_many_slashes(): void
    {
        config(['github.repo' => 'invalid/repo/value']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository format');

        (new LabelOrchestrator)->resolveRepo();
    }

    public function test_resolve_repo_throws_when_owner_segment_is_empty(): void
    {
        config(['github.repo' => '/repo']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository format');

        (new LabelOrchestrator)->resolveRepo();
    }

    public function test_resolve_repo_throws_when_repository_segment_is_empty(): void
    {
        config(['github.repo' => 'owner/']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository format');

        (new LabelOrchestrator)->resolveRepo();
    }

    public function test_resolve_repo_returns_owner_and_repository(): void
    {
        config(['github.repo' => 'my-org/my-repo']);

        [$owner, $repo] = (new LabelOrchestrator)->resolveRepo();

        $this->assertSame('my-org', $owner);
        $this->assertSame('my-repo', $repo);
    }
}
