<?php

namespace App\Services\GitHub;

use App\DataTransferObjects\GitHub\IssueCounts;
use App\DataTransferObjects\GitHub\PullRequestCounts;
use App\Exceptions\GitHubGraphQLException;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class GitHubService
{
    protected Client $client;
    protected string $token;
    private int $maxRetries;

    public function __construct()
    {
        $this->maxRetries = 3;
        $this->token = config('github.token');

        if (!$this->token) {
            throw new RuntimeException('Missing GitHub token in config.');
        }

        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(
            $this->getRetryDecider(),
            $this->getRetryDelay()
        ));

        $this->client = new Client([
            'base_uri' => 'https://api.github.com/graphql',
            'handler' => $stack,
            'headers' => [
                'Authorization' => "Bearer $this->token",
                'Content-Type' => 'application/json',
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

            // Retry on server errors (502, 503, 504)
            if ($response && in_array($response->getStatusCode(), [502, 503, 504], true)) {
                Log::warning("GitHub API returned {$response->getStatusCode()}. Retrying...");
                return true;
            }

            // Retry on connection errors
            if ($reason instanceof ConnectException) {
                Log::warning("Network error: {$reason->getMessage()}. Retrying...");
                return true;
            }

            // Retry on request errors (partial transfers, etc.)
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
            $delayMs = (2 ** $retries) * 2000; // 2s, 4s, 8s in milliseconds
            Log::info("Waiting {$delayMs}ms before retry...");
            return $delayMs;
        };
    }

    /**
     * @throws GitHubGraphQLException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function executeGraphQLQuery(string $query, array $variables = [], array $options = []): ?array
    {
        $response = $this->client->post('', [
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
            'timeout' => $options['timeout'] ?? 60,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
        ]);

        $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $rate = $json['data']['rateLimit'] ?? null;
        if ($rate && isset($rate['remaining'])) {
            // Proactive throttling based on remaining calls
            if ($rate['remaining'] === 0 && isset($rate['resetAt'])) {
                try {
                    $resetAt = new DateTime($rate['resetAt']);
                    $waitSeconds = max($resetAt->getTimestamp() - time(), 1);
                    Log::info("GitHub rate limit exceeded. Waiting for $waitSeconds seconds.");
                    sleep($waitSeconds);
                } catch (Exception $e) {
                    throw new RuntimeException("Invalid rateLimit.resetAt value: " . $rate['resetAt'] . ' ' . $e->getMessage());
                }
            } elseif ($rate['remaining'] < 100) {
                Log::info("GitHub rate limit very low ({$rate['remaining']} remaining). Adding 10s delay.");
                sleep(10);
            } elseif ($rate['remaining'] < 500) {
                Log::info("GitHub rate limit getting low ({$rate['remaining']} remaining). Adding 3s delay.");
                sleep(3);
            }
        }

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

    public function fetchIssueCount(string $owner, string $repo): IssueCounts
    {
        $query = file_get_contents(resource_path('graphql/github/github_issue_count.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'name' => $repo,
        ]);

        return IssueCounts::fromGraphQL($data);
    }

    public function fetchIssues(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_issues.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ]);

        $issues = $data['repository']['issues'] ?? [];
        $issues['rateLimit'] = $data['rateLimit'] ?? null;

        return $issues;
    }

    public function fetchPullRequestCount(string $owner, string $repo): PullRequestCounts
    {
        $query = file_get_contents(resource_path('graphql/github/github_pull_request_count.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'name' => $repo,
        ]);

        return PullRequestCounts::fromGraphQL($data);
    }

    public function fetchPullRequests(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_pull_requests.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ]);

        $pullRequests = $data['repository']['pullRequests'] ?? [];
        $pullRequests['rateLimit'] = $data['rateLimit'] ?? null;

        return $pullRequests;
    }

    public function fetchInteractionsForIssue(string $owner, string $repo, int $issueNumber): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_issue_interactions.graphql'));

        $variables = [
            'owner' => $owner,
            'name' => $repo,
            'number' => $issueNumber,
        ];

        $data = $this->executeGraphQLQuery($query, $variables);
        $node = $data['repository']['issueOrPullRequest'] ?? null;
        $interactions = [];

        if (!$node) {
            return [];
        }

        $isPR = $node['__typename'] === 'PullRequest';
        $author = $node['author']['login'] ?? 'unknown';

        // Created issue or PR
        if (isset($node['createdAt'])) {
            $interactions[] = [
                'author' => $author,
                'type' => $isPR ? 'created_pr' : 'created_issue',
                'date' => $node['createdAt'],
            ];
        }

        // Updated PR
        if ($isPR && isset($node['updatedAt']) && $node['updatedAt'] !== $node['createdAt']) {
            $interactions[] = [
                'author' => $author,
                'type' => 'updated_pr',
                'date' => $node['updatedAt'],
            ];
        }

        // Merged PR
        if ($isPR && isset($node['mergedAt']) && $node['mergedAt'] !== null) {
            $interactions[] = [
                'author' => $author,
                'type' => 'merged_pr',
                'date' => $node['mergedAt'],
            ];
        }

        // Comments
        foreach ($node['comments']['nodes'] ?? [] as $comment) {
            if (!isset($comment['createdAt'])) {
                continue;
            }
            $interactions[] = [
                'author' => $comment['author']['login'] ?? 'unknown',
                'type' => 'comment',
                'date' => $comment['createdAt'],
            ];
        }

        // Timeline events
        foreach ($node['timelineItems']['nodes'] ?? [] as $event) {
            if (!isset($event['createdAt'])) {
                continue;
            }

            $interactions[] = [
                'author' => $event['actor']['login'] ?? 'unknown',
                'type' => strtolower(str_replace('Event', '', $event['__typename'])),
                'date' => $event['createdAt'],
            ];
        }

        return $interactions;
    }

    public function fetchIssuesPaged(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_issues_paged.graphql'));

        $variables = [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ];

        $data = $this->executeGraphQLQuery($query, $variables);

        $issues = $data['repository']['issues']['nodes'] ?? [];
        $pageInfo = $data['repository']['issues']['pageInfo'] ?? [];

        return [
            'issues' => $issues,
            'endCursor' => $pageInfo['endCursor'] ?? null,
            'hasNextPage' => $pageInfo['hasNextPage'] ?? false,
        ];
    }

    /**
     * Fetch issues with their interactions (comments, timeline events) in a single query.
     * This eliminates N+1 API calls when syncing interactions.
     *
     * @throws GitHubGraphQLException
     * @throws JsonException
     */
    public function fetchIssuesWithInteractions(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_issues_with_interactions.graphql'));
        $variables = [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ];

        $data = $this->executeGraphQLQuery($query, $variables);
        $issues = $data['repository']['issues'] ?? [];
        $rateLimit = $data['rateLimit'] ?? null;

        return [
            'nodes' => $issues['nodes'] ?? [],
            'pageInfo' => $issues['pageInfo'] ?? [],
            'totalCount' => $issues['totalCount'] ?? 0,
            'rateLimit' => $rateLimit,
        ];
    }

    /**
     * Extract interactions from an issue node that includes inline comments and timeline items.
     */
    public function extractInteractionsFromIssue(array $issue): array
    {
        $interactions = [];
        $author = $issue['author']['login'] ?? 'unknown';
        $issueNumber = $issue['number'];

        // Created issue
        if (isset($issue['createdAt'])) {
            $interactions[] = [
                'author' => $author,
                'type' => 'created_issue',
                'date' => $issue['createdAt'],
            ];
        }

        // Comments
        foreach ($issue['comments']['nodes'] ?? [] as $comment) {
            if (!isset($comment['createdAt'])) {
                continue;
            }
            $interactions[] = [
                'author' => $comment['author']['login'] ?? 'unknown',
                'type' => 'comment',
                'date' => $comment['createdAt'],
            ];
        }

        // Timeline events
        foreach ($issue['timelineItems']['nodes'] ?? [] as $event) {
            if (!isset($event['createdAt'])) {
                continue;
            }

            $interactions[] = [
                'author' => $event['actor']['login'] ?? 'unknown',
                'type' => strtolower(str_replace('Event', '', $event['__typename'])),
                'date' => $event['createdAt'],
            ];
        }

        return $interactions;
    }

    /**
     * Fetch issues with their timeline events in a single query.
     * This eliminates N+1 API calls when syncing events.
     */
    public function fetchIssuesWithEvents(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_issues_with_events.graphql'));

        $variables = [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ];

        // Use longer timeout for this complex query (large event payloads on later pages)
        $data = $this->executeGraphQLQuery($query, $variables, [
            'timeout' => 120,           // 2 minutes for large payloads
            'connect_timeout' => 15,    // 15 seconds for connection
        ]);

        $issues = $data['repository']['issues'] ?? [];
        $rateLimit = $data['rateLimit'] ?? null;

        return [
            'nodes' => $issues['nodes'] ?? [],
            'pageInfo' => $issues['pageInfo'] ?? [],
            'totalCount' => $issues['totalCount'] ?? 0,
            'rateLimit' => $rateLimit,
        ];
    }

    /**
     * Extract events from an issue node that includes inline timeline items.
     */
    public function extractEventsFromIssue(array $issue): array
    {
        $events = [];

        foreach ($issue['timelineItems']['nodes'] ?? [] as $event) {
            if (!isset($event['createdAt'])) {
                continue;
            }

            $events[] = [
                'type' => strtolower(str_replace('Event', '', $event['__typename'])),
                'actor' => $event['actor']['login'] ?? 'unknown',
                'created_at' => $event['createdAt'],
            ];
        }

        return $events;
    }

    public function fetchEventsForIssue(string $owner, string $repo, int $number): array
    {
        $restClient = $this->getRestClient();

        $events = [];
        $url = "repos/$owner/$repo/issues/$number/timeline";

        try {
            $response = $restClient->get($url);
            $raw = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            foreach ($raw as $event) {
                if (!isset($event['event'], $event['created_at'])) {
                    continue;
                }

                $events[] = [
                    'type' => $event['event'],
                    'actor' => $event['actor']['login'] ?? 'unknown',
                    'created_at' => $event['created_at'],
                ];
            }
        } catch (\Throwable $e) {
            \Log::error("Failed to fetch events for issue #$number", ['exception' => $e]);
        }

        return $events;
    }

    public function getRateLimit(): array
    {
        $restClient = $this->getRestClient();

        try {
            $response = $restClient->get('rate_limit');
            $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            return $json['rate'] ?? [];
        } catch (Exception $e) {
            Log::warning('Failed to fetch GitHub rate limit', ['exception' => $e]);
            return ['remaining' => 0];
        }
    }

    public function createLabel(string $owner, string $repo, string $label): int
    {
        $restClient = $this->getRestClient();


        $isAlreadyExists = $this->checkIsAlreadyExists($owner, $repo, $label);
        if ($isAlreadyExists) {
            return 0;
        }
        $url = "repos/$owner/$repo/labels";

        try {
            $response = $restClient->request('POST', $url, [
                'json' => [
                    'name' => $label
                ]
            ]);
            json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            return 1;
        } catch (Exception $e) {
            Log::error('Failed to create label', ['exception' => $e]);
            return 0;
        }
    }

    public function renameLabel(string $owner, string $repo, string $oldName, string $newName): int
    {
        $restClient = $this->getRestClient();

        $url = "repos/$owner/$repo/labels/$oldName";

        try {
            $restClient->request('PATCH', $url, [
                'json' => [
                    'new_name' => $newName
                ]
            ]);
            return 1;
        } catch (Exception $e) {
            Log::error('Failed to rename label', ['exception' => $e]);
            return 0;
        }
    }

    private function checkIsAlreadyExists(string $owner, string $repo, string $label): bool
    {
        $restClient = $this->getRestClient();

        $url = "repos/$owner/$repo/labels/$label";
        try {
            $response = $restClient->get($url);
            $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            if ($json['name'] == $label) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            Log::error('Failed to check is label already exists', ['exception' => $e]);
            return false;
        }
    }
    
    private function getRestClient(): Client
    {
        return new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);
    }
}
