<?php

namespace App\Services\GitHub;

use App\DataTransferObjects\GitHub\IssueCounts;
use App\DataTransferObjects\GitHub\PullRequestCounts;
use App\Exceptions\GitHubGraphQLException;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
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

        $this->client = new Client([
            'base_uri' => 'https://api.github.com/graphql',
            'headers' => [
                'Authorization' => "Bearer $this->token",
                'Content-Type' => 'application/json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);
    }

    private function executeGraphQLQuery(string $query, array $variables = []): ?array
    {
        $retryCount = 0;

        do {
            try {
                $response = $this->client->post('', [
                    'json' => [
                        'query' => $query,
                        'variables' => $variables,
                    ],
                ]);
            } catch (\GuzzleHttp\Exception\ServerException $e) {
                // Retry on 502, 503, 504 errors
                $statusCode = $e->getResponse()->getStatusCode();
                if (in_array($statusCode, [502, 503, 504]) && $retryCount < $this->maxRetries) {
                    $waitSeconds = pow(2, $retryCount) * 5; // Exponential backoff: 5s, 10s, 20s
                    Log::warning("GitHub API returned $statusCode. Retrying in {$waitSeconds}s...");
                    sleep($waitSeconds);
                    $retryCount++;
                    continue;
                }
                throw $e;
            }

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

                    $retryCount++;
                    continue;
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
        } while (++$retryCount < $this->maxRetries);

        return null;
    }

    public function fetchIssueCount(string $owner, string $repo): IssueCounts
    {
        $query = file_get_contents(resource_path('graphql/github/github_issue_count.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'repo' => $repo,
        ]);

        return IssueCounts::fromGraphQL($data);
    }

    public function fetchIssues(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_issues.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'repo' => $repo,
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
            'repo' => $repo,
        ]);

        return PullRequestCounts::fromGraphQL($data);
    }

    public function fetchPullRequests(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_pull_requests.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'repo' => $repo,
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
     */
    public function fetchIssuesWithInteractions(string $owner, string $repo, ?string $cursor = null): array
    {
        $query = file_get_contents(resource_path('graphql/github/github_issues_with_interactions.graphql'));

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'repo' => $repo,
            'cursor' => $cursor,
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

        $data = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'repo' => $repo,
            'cursor' => $cursor,
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
        $restClient = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);

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
        $restClient = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);

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
        $restClient = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);

        $isAlreadyExists = $this->checkIsAlreadyExists($owner, $repo, $label);
        if ($isAlreadyExists) {
            return 0;
        }
        $url = "repos/$owner/$repo/labels";

        try {
            $restClient->request('POST', $url, [
                'json' => [
                    'name' => $label
                ]
            ]);
            return 1;
        } catch (Exception $e) {
            Log::error('Failed to create label', ['exception' => $e]);
            return 0;
        }
    }

    public function renameLabel(string $owner, string $repo, string $oldName, string $newName): int
    {
        $restClient = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);

        $url = "repos/$owner/$repo/labels/" . rawurlencode($oldName);

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
        $restClient = new Client([
            'base_uri' => 'https://api.github.com/',
            'headers' => [
                'Authorization' => "Bearer {$this->token}",
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Laravel-GitHubSync/1.0',
            ],
        ]);

        $url = "repos/$owner/$repo/labels/$label";
        try {
            $response = $restClient->get($url);
            $json = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            if ($json['name'] === $label) {
                return true;
            }
            return false;
        } catch (RequestException | Exception $e) {
            Log::error('Failed to check is label already exists', ['exception' => $e]);
            return false;
        }
    }
}
