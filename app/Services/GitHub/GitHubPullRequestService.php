<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use App\DataTransferObjects\GitHub\PullRequestCounts;
use App\Exceptions\GitHubGraphQLException;

class GitHubPullRequestService
{
    public function __construct(private readonly GitHubConnection $connection)
    {
    }

    public function fetchPullRequestCount(string $owner, string $repo): PullRequestCounts
    {
        $data = $this->executeQuery('github_pull_request_count.graphql', [
            'owner' => $owner,
            'name' => $repo,
        ]);

        return PullRequestCounts::fromGraphQL($data);
    }

    public function fetchPullRequests(string $owner, string $repo, ?string $cursor = null): array
    {
        $data = $this->executeQuery('github_pull_requests.graphql', [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ]);

        $pullRequests = $data['repository']['pullRequests'] ?? [];
        $pullRequests['rateLimit'] = $data['rateLimit'] ?? null;

        return $pullRequests;
    }

    /**
     * @throws GitHubGraphQLException
     * @throws \JsonException
     */
    private function executeQuery(string $queryFile, array $variables): ?array
    {
        $query = file_get_contents(resource_path('graphql/github/' . $queryFile));
        if ($query === false) {
            throw new \RuntimeException("Failed to load GraphQL query file: {$queryFile}");
        }

        return $this->connection->executeGraphQL($query, $variables);
    }
}
