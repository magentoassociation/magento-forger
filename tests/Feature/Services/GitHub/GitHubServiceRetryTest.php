<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\Services\GitHub\GitHubConnection;
use App\Services\GitHub\GitHubIssueService;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubServiceRetryTest extends TestCase
{
    private function createServiceWithMockHandler(MockHandler $mock): GitHubIssueService
    {
        config()->set('github.token', 'test-token');

        return new GitHubIssueService(
            new GitHubConnection(graphQlHandler: HandlerStack::create($mock))
        );
    }

    public function test_retries_on_503_server_error(): void
    {
        $mock = new MockHandler([
            new Response(503),
            new Response(503),
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                    'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createServiceWithMockHandler($mock);
        $result = $service->fetchIssuesPaged('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('issues', $result);
    }

    public function test_retries_on_502_bad_gateway(): void
    {
        $mock = new MockHandler([
            new Response(502),
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                    'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createServiceWithMockHandler($mock);
        $result = $service->fetchIssuesPaged('laravel', 'framework');

        $this->assertIsArray($result);
    }

    public function test_retries_on_504_gateway_timeout(): void
    {
        $mock = new MockHandler([
            new Response(504),
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                    'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = $this->createServiceWithMockHandler($mock);
        $result = $service->fetchIssuesPaged('laravel', 'framework');

        $this->assertIsArray($result);
    }

    public function test_fails_after_max_retries(): void
    {
        $this->expectException(ServerException::class);

        $mock = new MockHandler([
            new Response(503),
            new Response(503),
            new Response(503),
            new Response(503),
        ]);

        $service = $this->createServiceWithMockHandler($mock);
        $service->fetchIssuesPaged('laravel', 'framework');
    }

    public function test_successful_request_without_retries(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                    'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
                ],
            ])),
        ]);

        $service = $this->createServiceWithMockHandler($mock);
        $result = $service->fetchIssuesPaged('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('issues', $result);
    }
}
