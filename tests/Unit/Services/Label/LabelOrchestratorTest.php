<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Label;

use App\Services\GitHub\GitHubLabelService;
use App\Services\Label\LabelOrchestrator;
use Mockery;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Tests\TestCase;

class LabelOrchestratorTest extends TestCase
{
    private LabelOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orchestrator = new LabelOrchestrator(Mockery::mock(GitHubLabelService::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_process_returns_error_when_no_supported_worksheets_found(): void
    {
        config(['github.repo' => 'my-org/my-repo']);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('unsupported-tab');

        $path = tempnam(sys_get_temp_dir(), 'label_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        try {
            $result = $this->orchestrator->process($path);
        } finally {
            unlink($path);
        }

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['renamed']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('No supported worksheets found', $result['errors'][0]);
    }

    public function test_resolve_repo_throws_when_configuration_is_missing(): void
    {
        config(['github.repo' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GitHub repository is not configured');

        $this->orchestrator->resolveRepo();
    }

    public function test_resolve_repo_throws_when_configuration_has_too_many_slashes(): void
    {
        config(['github.repo' => 'invalid/repo/value']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository format');

        $this->orchestrator->resolveRepo();
    }

    public function test_resolve_repo_throws_when_owner_segment_is_empty(): void
    {
        config(['github.repo' => '/repo']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository format');

        $this->orchestrator->resolveRepo();
    }

    public function test_resolve_repo_throws_when_repository_segment_is_empty(): void
    {
        config(['github.repo' => 'owner/']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid GitHub repository format');

        $this->orchestrator->resolveRepo();
    }

    public function test_resolve_repo_returns_owner_and_repository(): void
    {
        config(['github.repo' => 'my-org/my-repo']);

        [$owner, $repo] = $this->orchestrator->resolveRepo();

        $this->assertSame('my-org', $owner);
        $this->assertSame('my-repo', $repo);
    }
}
