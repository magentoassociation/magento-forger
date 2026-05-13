<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\GitHub;

use App\Services\GitHub\GitHubConnection;
use App\Services\GitHub\GitHubLabelService;
use ReflectionClass;
use Tests\TestCase;

class GitHubServiceLabelUrlTest extends TestCase
{
    public function test_build_label_url_encodes_spaces_and_colons(): void
    {
        config()->set('github.token', 'test-token');

        $service = new GitHubLabelService(new GitHubConnection());
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildLabelUrl');
        $method->setAccessible(true);

        $url = $method->invoke($service, 'owner', 'repo', 'needs triage: backend');

        $this->assertSame(
            'repos/owner/repo/labels/needs%20triage%3A%20backend',
            $url
        );
    }
}
