<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\Services\GitHub\GitHubConnection;
use App\Services\GitHub\GitHubIssueService;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubServiceRetryTest extends TestCase
{
    private function createServiceWithMockHandler(MockHandler $mock, ?\Closure $retryDelay = null): GitHubIssueService
    {
        config()->set('github.token', 'test-token');

        return new GitHubIssueService(
            new GitHubConnection(
                graphQlHandler: HandlerStack::create($mock),
                retryDelayOverride: $retryDelay ?? fn () => 0,
            )
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
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
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
        $result = $service->fetchIssues('laravel', 'framework');

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
        $result = $service->fetchIssues('laravel', 'framework');

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
        $service->fetchIssues('laravel', 'framework');
    }

    public function test_retries_on_403_secondary_rate_limit_with_retry_after_header(): void
    {
        $successBody = json_encode([
            'data' => [
                'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
            ],
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([
            new Response(403, ['Retry-After' => '1']),
            new Response(200, [], $successBody),
        ]);

        $service = $this->createServiceWithMockHandler($mock, fn () => 0);
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
    }

    public function test_does_not_retry_on_403_without_retry_after_header(): void
    {
        $this->expectException(ClientException::class);

        $mock = new MockHandler([
            new Response(403),
            new Response(200, [], json_encode(['data' => []])),
        ]);

        $service = $this->createServiceWithMockHandler($mock);
        $service->fetchIssues('laravel', 'framework');
    }

    public function test_retries_on_429_too_many_requests(): void
    {
        $successBody = json_encode([
            'data' => [
                'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
            ],
        ], JSON_THROW_ON_ERROR);

        $mock = new MockHandler([
            new Response(429, ['Retry-After' => '1']),
            new Response(200, [], $successBody),
        ]);

        $service = $this->createServiceWithMockHandler($mock, fn () => 0);
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
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
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
    }
}
