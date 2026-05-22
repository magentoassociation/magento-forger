<?php
/*
 * @author Laura Folco <me@laurafolco.com> 2026
 */
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub\Mocks;

use App\Services\GitHub\GitHubService;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

class GitHubServiceMockFactory
{
    /**
     * Create a GitHubService with a mocked Guzzle client.
     */
    public static function create(MockHandler $mock): GitHubService
    {
        config(['github.token' => 'test-token']);
        $handler = HandlerStack::create($mock);
        $handler->push(Middleware::retry(
            function ($retries, $request, $response, $reason) {
                if ($retries >= 3) {
                    return false;
                }
                if ($response && in_array($response->getStatusCode(), [502, 503, 504], true)) {
                    return true;
                }
                if ($reason instanceof ConnectException) {
                    return true;
                }
                if ($reason instanceof RequestException && !$reason->hasResponse()) {
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
}

