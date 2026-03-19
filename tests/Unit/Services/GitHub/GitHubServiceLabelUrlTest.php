<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GitHub;

use App\Services\GitHub\GitHubService;
use ReflectionClass;
use Tests\TestCase;

class GitHubServiceLabelUrlTest extends TestCase
{
    public function test_build_label_url_encodes_spaces_and_colons(): void
    {
        config()->set('github.token', 'test-token');

        $service = new GitHubService();
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