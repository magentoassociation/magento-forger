<?php
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\Services\GitHub\GitHubService;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubServiceRetryTest extends TestCase
{
    /**
     * Create a GitHubService with a mocked Guzzle client.
     */
    private function createServiceWithMockHandler(MockHandler $mock): GitHubService
    {
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::retry(
            function ($retries, $request, $response, $reason) {
                if ($retries >= 3) {
                    return false;
                }
                if ($response && in_array($response->getStatusCode(), [502, 503, 504], true)) {
                    return true;
                }
                return false;
            },
            function ($retries) {
                return (2 ** $retries) * 2000;
            }
        ));

        $service = new GitHubService();
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.github.com/graphql',
            'handler' => $handler,
            'headers' => [
                'Authorization' => 'Bearer test-token',
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);

        // Use reflection to set the protected client property
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        return $service;
    }

    /**
     * Test that the service retries on 503 Server Unavailable errors.
     */
    public function test_retries_on_503_server_error(): void
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

        $service = $this->createServiceWithMockHandler($mock);
        $result = $service->fetchIssuesPaged('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('issues', $result);
    }

    /**
     * Test that the service retries on 502 Bad Gateway errors.
     */
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

    /**
     * Test that the service retries on 504 Gateway Timeout errors.
     */
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

    /**
     * Test that the service eventually fails after max retries.
     */
    public function test_fails_after_max_retries(): void
    {
        $this->expectException(ServerException::class);

        $mock = new MockHandler([
            new Response(503),
            new Response(503),
            new Response(503),
            new Response(503), // Will fail after 3 retries
        ]);

        $service = $this->createServiceWithMockHandler($mock);
        $service->fetchIssuesPaged('laravel', 'framework');
    }

    /**
     * Test successful request without retries.
     */
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

