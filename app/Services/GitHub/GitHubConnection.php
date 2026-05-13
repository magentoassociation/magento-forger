<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use App\Exceptions\GitHubGraphQLException;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class GitHubConnection
{
    private readonly Client $graphQlClient;

    private readonly Client $restClient;

    public function __construct(
        ?Client $graphQlClient = null,
        ?Client $restClient = null,
        ?HandlerStack $graphQlHandler = null,
        private readonly int $maxRetries = 3,
    ) {
        $token = null;

        if ($graphQlClient === null || $restClient === null) {
            $token = $this->resolveToken();
        }

        $this->graphQlClient = $graphQlClient ?? $this->createGraphQlClient($token, $graphQlHandler);
        $this->restClient = $restClient ?? $this->createRestClient($token);
    }

    /**
     * @throws GitHubGraphQLException
     * @throws JsonException
     */
    public function executeGraphQL(string $query, array $variables = [], array $options = []): ?array
    {
        $response = $this->graphQlClient->post('', [
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
            'timeout' => $options['timeout'] ?? 60,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
        ]);

        $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $this->handleRateLimit($json);

        if (isset($json['errors'])) {
            throw new GitHubGraphQLException(
                'GitHub GraphQL API error',
                [
                    'status' => $response->getStatusCode(),
                    'errors' => $json['errors'],
                    'query' => $query,
                    'variables' => $variables,
                ]
            );
        }

        return $json['data'] ?? null;
    }

    public function getRateLimit(): array
    {
        try {
            $response = $this->restClient->get('rate_limit');
            $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            return $json['rate'] ?? [];
        } catch (Exception $exception) {
            Log::warning('Failed to fetch GitHub rate limit', ['exception' => $exception]);

            return ['remaining' => 0];
        }
    }

    public function rest(): Client
    {
        return $this->restClient;
    }

    private function resolveToken(): string
    {
        $token = trim((string) config('github.token'));

        if ($token === '') {
            throw new RuntimeException('Missing GitHub token in config.');
        }

        return $token;
    }

    private function createGraphQlClient(?string $token, ?HandlerStack $handler = null): Client
    {
        if ($token === null) {
            throw new RuntimeException('Missing GitHub token in config.');
        }

        $stack = $handler ?? HandlerStack::create();
        $stack->push(Middleware::retry(
            $this->getRetryDecider(),
            $this->getRetryDelay()
        ));

        return new Client([
            'base_uri' => 'https://api.github.com/graphql',
            'handler' => $stack,
            'headers' => [
                'Authorization' => "Bearer $token",
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);
    }

    private function createRestClient(?string $token): Client
    {
        if ($token === null) {
            throw new RuntimeException('Missing GitHub token in config.');
        }

        return new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);
    }

    /**
     * Determine if a request should be retried based on response or exception.
     */
    private function getRetryDecider(): callable
    {
        return function ($retries, $request, $response, $reason) {
            if ($retries >= $this->maxRetries) {
                return false;
            }

            if ($response && in_array($response->getStatusCode(), [502, 503, 504], true)) {
                Log::warning("GitHub API returned {$response->getStatusCode()}. Retrying...");

                return true;
            }

            if ($reason instanceof ConnectException) {
                Log::warning("Network error: {$reason->getMessage()}. Retrying...");

                return true;
            }

            if ($reason instanceof RequestException) {
                Log::warning("Request error: {$reason->getMessage()}. Retrying...");

                return true;
            }

            return false;
        };
    }

    /**
     * Calculate delay in milliseconds for exponential backoff.
     */
    private function getRetryDelay(): callable
    {
        return static function ($retries) {
            $delayMs = (2 ** $retries) * 2000;
            Log::info("Waiting {$delayMs}ms before retry...");

            return $delayMs;
        };
    }

    private function handleRateLimit(array $response): void
    {
        $rate = $response['data']['rateLimit'] ?? null;

        if (! $rate || ! isset($rate['remaining'])) {
            return;
        }

        if ($rate['remaining'] === 0 && isset($rate['resetAt'])) {
            try {
                $resetAt = new DateTime($rate['resetAt']);
                $waitSeconds = max($resetAt->getTimestamp() - time(), 1);
                Log::info("GitHub rate limit exceeded. Waiting for $waitSeconds seconds.");
                sleep($waitSeconds);
            } catch (Exception $exception) {
                throw new RuntimeException(
                    "Invalid rateLimit.resetAt value: {$rate['resetAt']}. Parse error: {$exception->getMessage()}"
                );
            }

            return;
        }

        if ($rate['remaining'] < 100) {
            Log::info("GitHub rate limit very low ({$rate['remaining']} remaining). Adding 10s delay.");
            sleep(10);

            return;
        }

        if ($rate['remaining'] < 500) {
            Log::info("GitHub rate limit getting low ({$rate['remaining']} remaining). Adding 3s delay.");
            sleep(3);
        }
    }
}
