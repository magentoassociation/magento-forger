<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use App\DataTransferObjects\GitHub\IssueCounts;

class GitHubIssueService
{
    public function __construct(private readonly GitHubConnection $connection) {}

    public function fetchIssueCount(string $owner, string $repo): IssueCounts
    {
        $data = $this->executeQuery('github_issue_count.graphql', [
            'owner' => $owner,
            'name' => $repo,
        ]);

        return IssueCounts::fromGraphQL($data);
    }

    public function fetchIssues(string $owner, string $repo, ?string $cursor = null): array
    {
        $data = $this->executeQuery('github_issues.graphql', [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ]);

        $issues = $data['repository']['issues'] ?? [];
        $issues['rateLimit'] = $data['rateLimit'] ?? null;

        return $issues;
    }

    public function fetchIssuesPaged(string $owner, string $repo, ?string $cursor = null): array
    {
        $data = $this->executeQuery('github_issues_paged.graphql', [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ]);

        $issues = $data['repository']['issues']['nodes'] ?? [];
        $pageInfo = $data['repository']['issues']['pageInfo'] ?? [];

        return [
            'issues' => $issues,
            'endCursor' => $pageInfo['endCursor'] ?? null,
            'hasNextPage' => $pageInfo['hasNextPage'] ?? false,
        ];
    }

    public function fetchIssuesWithInteractions(string $owner, string $repo, ?string $cursor = null): array
    {
        $data = $this->executeQuery('github_issues_with_interactions.graphql', [
            'owner' => $owner,
            'name' => $repo,
            'cursor' => $cursor,
        ]);

        $issues = $data['repository']['issues'] ?? [];

        return [
            'nodes' => $issues['nodes'] ?? [],
            'pageInfo' => $issues['pageInfo'] ?? [],
            'totalCount' => $issues['totalCount'] ?? 0,
            'rateLimit' => $data['rateLimit'] ?? null,
        ];
    }

    public function fetchIssuesWithEvents(string $owner, string $repo, ?string $cursor = null): array
    {
        $data = $this->executeQuery(
            'github_issues_with_events.graphql',
            [
                'owner' => $owner,
                'name' => $repo,
                'cursor' => $cursor,
            ],
            [
                'timeout' => 120,
                'connect_timeout' => 15,
            ]
        );

        $issues = $data['repository']['issues'] ?? [];

        return [
            'nodes' => $issues['nodes'] ?? [],
            'pageInfo' => $issues['pageInfo'] ?? [],
            'totalCount' => $issues['totalCount'] ?? 0,
            'rateLimit' => $data['rateLimit'] ?? null,
        ];
    }

    private function executeQuery(string $queryFile, array $variables, array $options = []): ?array
    {
        $query = file_get_contents(resource_path('graphql/github/'.$queryFile));

        return $this->connection->executeGraphQL($query, $variables, $options);
    }
}
