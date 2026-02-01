<?php
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\Exceptions\GitHubGraphQLException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Tests\Feature\Services\GitHub\Mocks\GitHubServiceMockFactory;
use Tests\TestCase;

class GitHubServiceRetryTest extends TestCase
{
    /**
     * Test that the service retries on 503 Server Unavailable errors.
     *
     * @throws GitHubGraphQLException
     * @throws GuzzleException
     * @throws \JsonException
     */
    public function testRetriesOn503ServerUnavailable(): void
    {
        $mock = new MockHandler([
            new Response(503),  // First request fails with 503
            new Response(503),  // Second request fails with 503
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                    'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
                ],
            ], JSON_THROW_ON_ERROR)), // Third succeeds
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertArrayHasKey('rateLimit', $result);
    }

    /**
     * Test that the service retries on 502 Bad Gateway errors.
     */
    public function testRetriesOn502BadGateway(): void
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

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
    }

    /**
     * Test that the service retries on 504 Gateway Timeout errors.
     *
     * @throws \JsonException
     */
    public function testRetriesOn504GatewayTimeout(): void
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

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
    }

    /**
     * Test that the service eventually fails after max retries.
     */
    public function testFailsAfterMaxRetries(): void
    {
        $this->expectException(ServerException::class);

        $mock = new MockHandler([
            new Response(503),
            new Response(503),
            new Response(503),
            new Response(503), // Will fail after 3 retries
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $service->fetchIssues('laravel', 'framework');
    }

    /**
     * Test successful request without retries.
     */
    public function testSuccessfulRequestWithoutRetries(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                    'repository' => ['issues' => ['nodes' => [], 'pageInfo' => []]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertArrayHasKey('rateLimit', $result);
    }
}

