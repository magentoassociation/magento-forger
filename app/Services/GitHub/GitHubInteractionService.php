<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use App\Exceptions\GitHubGraphQLException;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class GitHubInteractionService
{
    public function __construct(private readonly GitHubConnection $connection) {}

    public function fetchInteractionsForIssue(string $owner, string $repo, int $issueNumber): array
    {
        $baseVars = ['owner' => $owner, 'name' => $repo, 'number' => $issueNumber];

        $data = $this->executeQuery('github_issue_interactions.graphql', $baseVars);

        $node = $data['repository']['issueOrPullRequest'] ?? null;
        $interactions = [];

        if (! $node) {
            return [];
        }

        $isPullRequest = $node['__typename'] === 'PullRequest';

        while ($node['comments']['pageInfo']['hasNextPage'] ?? false) {
            $more = $this->executeQuery('github_issue_interactions.graphql', $baseVars + ['commentsCursor' => $node['comments']['pageInfo']['endCursor']]);
            $moreNode = $more !== null ? ($more['repository']['issueOrPullRequest'] ?? null) : null;
            if (! $moreNode) {
                break;
            }
            $node['comments']['nodes'] = array_merge($node['comments']['nodes'] ?? [], $moreNode['comments']['nodes'] ?? []);
            $node['comments']['pageInfo'] = $moreNode['comments']['pageInfo'] ?? ['hasNextPage' => false];
        }

        if (! $isPullRequest) {
            while ($node['timelineItems']['pageInfo']['hasNextPage'] ?? false) {
                $more = $this->executeQuery('github_issue_interactions.graphql', $baseVars + ['timelinesCursor' => $node['timelineItems']['pageInfo']['endCursor']]);
                $moreNode = $more !== null ? ($more['repository']['issueOrPullRequest'] ?? null) : null;
                if (! $moreNode) {
                    break;
                }
                $node['timelineItems']['nodes'] = array_merge($node['timelineItems']['nodes'] ?? [], $moreNode['timelineItems']['nodes'] ?? []);
                $node['timelineItems']['pageInfo'] = $moreNode['timelineItems']['pageInfo'] ?? ['hasNextPage' => false];
            }
        }

        $author = $node['author']['login'] ?? 'unknown';

        if (isset($node['createdAt'])) {
            $interactions[] = [
                'author' => $author,
                'type' => $isPullRequest ? 'created_pr' : 'created_issue',
                'date' => $node['createdAt'],
            ];
        }

        if ($isPullRequest && isset($node['updatedAt']) && $node['updatedAt'] !== $node['createdAt']) {
            $interactions[] = [
                'author' => $author,
                'type' => 'updated_pr',
                'date' => $node['updatedAt'],
            ];
        }

        if ($isPullRequest && isset($node['mergedAt']) && $node['mergedAt'] !== null) {
            $interactions[] = [
                'author' => $author,
                'type' => 'merged_pr',
                'date' => $node['mergedAt'],
            ];
        }

        return $this->processComments($node, $interactions);
    }

    public function fetchAllInteractionsFromIssue(array $issue, string $owner, string $repo): array
    {
        $interactions = $this->extractInteractionsFromIssue($issue);
        $commentsPageInfo = $issue['comments']['pageInfo'] ?? [];
        $timelinePageInfo = $issue['timelineItems']['pageInfo'] ?? [];

        while ($commentsPageInfo['hasNextPage'] ?? false) {
            $data = $this->executeQuery('github_issue_comments.graphql', [
                'owner' => $owner,
                'name' => $repo,
                'number' => $issue['number'],
                'cursor' => $commentsPageInfo['endCursor'],
            ]);
            $comments = $data !== null ? ($data['repository']['issue']['comments'] ?? []) : [];
            $commentsPageInfo = $comments['pageInfo'] ?? ['hasNextPage' => false];
            $interactions = array_merge($interactions, $this->processComments(['comments' => $comments, 'timelineItems' => ['nodes' => []]], []));
        }

        while ($timelinePageInfo['hasNextPage'] ?? false) {
            $data = $this->executeQuery('github_issue_timeline_items.graphql', [
                'owner' => $owner,
                'name' => $repo,
                'number' => $issue['number'],
                'cursor' => $timelinePageInfo['endCursor'],
            ]);
            $timelineItems = $data !== null ? ($data['repository']['issue']['timelineItems'] ?? []) : [];
            $timelinePageInfo = $timelineItems['pageInfo'] ?? ['hasNextPage' => false];
            $interactions = array_merge($interactions, $this->processComments(['comments' => ['nodes' => []], 'timelineItems' => $timelineItems], []));
        }

        return $interactions;
    }

    public function extractInteractionsFromIssue(array $issue): array
    {
        $interactions = [];
        $author = $issue['author']['login'] ?? 'unknown';

        if (isset($issue['createdAt'])) {
            $interactions[] = [
                'author' => $author,
                'type' => 'created_issue',
                'date' => $issue['createdAt'],
            ];
        }

        return $this->processComments($issue, $interactions);
    }

    public function fetchAllEventsFromIssue(array $issue, string $owner, string $repo): array
    {
        $events = $this->extractEventsFromIssue($issue);
        $pageInfo = $issue['timelineItems']['pageInfo'] ?? [];

        while ($pageInfo['hasNextPage'] ?? false) {
            $data = $this->executeQuery('github_issue_timeline_items.graphql', [
                'owner' => $owner,
                'name' => $repo,
                'number' => $issue['number'],
                'cursor' => $pageInfo['endCursor'],
            ]);
            $timelineItems = $data !== null ? ($data['repository']['issue']['timelineItems'] ?? []) : [];
            $pageInfo = $timelineItems['pageInfo'] ?? ['hasNextPage' => false];
            $events = array_merge($events, $this->extractEventsFromIssue(['timelineItems' => $timelineItems]));
        }

        return $events;
    }

    public function extractEventsFromIssue(array $issue): array
    {
        $events = [];

        foreach ($issue['timelineItems']['nodes'] ?? [] as $event) {
            if (! isset($event['createdAt'])) {
                continue;
            }

            $events[] = [
                'type' => strtolower(str_replace('Event', '', $event['__typename'])),
                'actor' => $event['actor']['login'] ?? 'unknown',
                'created_at' => $event['createdAt'],
                'label' => $event['label']['name'] ?? null,
            ];
        }

        return $events;
    }

    public function fetchEventsForIssue(string $owner, string $repo, int $number): array
    {
        $events = [];
        $url = "repos/$owner/$repo/issues/$number/timeline";

        try {
            $response = $this->connection->rest()->get($url);
            $raw = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            foreach ($raw as $event) {
                if (! isset($event['event'], $event['created_at'])) {
                    continue;
                }

                $events[] = [
                    'type' => $event['event'],
                    'actor' => $event['actor']['login'] ?? 'unknown',
                    'created_at' => $event['created_at'],
                ];
            }
        } catch (Throwable $exception) {
            Log::error("Failed to fetch events for issue #$number", ['exception' => $exception]);
        }

        return $events;
    }

    /**
     * @throws GitHubGraphQLException
     * @throws JsonException
     */
    private function executeQuery(string $queryFile, array $variables, array $options = []): ?array
    {
        $query = file_get_contents(resource_path('graphql/github/'.$queryFile));

        if ($query === false) {
            throw new \RuntimeException("Failed to load GraphQL query file: {$queryFile}");
        }

        return $this->connection->executeGraphQL($query, $variables, $options);
    }

    private function processComments(array $issue, array $interactions): array
    {
        foreach ($issue['comments']['nodes'] ?? [] as $comment) {
            if (! isset($comment['createdAt'])) {
                continue;
            }

            $interactions[] = [
                'author' => $comment['author']['login'] ?? 'unknown',
                'type' => 'comment',
                'date' => $comment['createdAt'],
            ];
        }

        foreach ($issue['timelineItems']['nodes'] ?? [] as $event) {
            if (! isset($event['createdAt'])) {
                continue;
            }

            $interactions[] = [
                'author' => $event['actor']['login'] ?? 'unknown',
                'type' => strtolower(str_replace('Event', '', $event['__typename'])),
                'date' => $event['createdAt'],
                'label' => $event['label']['name'] ?? null,
            ];
        }

        return $interactions;
    }
}
