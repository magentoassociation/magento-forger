<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class GitHubInteractionService
{
    public function __construct(private readonly GitHubConnection $connection) {}

    public function fetchInteractionsForIssue(string $owner, string $repo, int $issueNumber): array
    {
        $data = $this->executeQuery('github_issue_interactions.graphql', [
            'owner' => $owner,
            'name' => $repo,
            'number' => $issueNumber,
        ]);

        $node = $data['repository']['issueOrPullRequest'] ?? null;
        $interactions = [];

        if (! $node) {
            return [];
        }

        $isPullRequest = $node['__typename'] === 'PullRequest';
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

    private function executeQuery(string $queryFile, array $variables): ?array
    {
        $query = file_get_contents(resource_path('graphql/github/'.$queryFile));

        return $this->connection->executeGraphQL($query, $variables);
    }

    /**
     * @param array $issue
     * @param array $interactions
     * @return array
     */
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
            ];
        }

        return $interactions;
    }
}
