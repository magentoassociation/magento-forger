<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use App\DataTransferObjects\GitHub\IssueCounts;
use App\DataTransferObjects\GitHub\PullRequestCounts;
use App\Exceptions\GitHubGraphQLException;
use JsonException;

class GitHubService
{
    public function __construct(
        private readonly GitHubConnection $connection,
        private readonly GitHubIssueService $issues,
        private readonly GitHubPullRequestService $pullRequests,
        private readonly GitHubInteractionService $interactions,
        private readonly GitHubLabelService $labels,
    ) {}

    public function fetchIssueCount(string $owner, string $repo): IssueCounts
    {
        return $this->issues->fetchIssueCount($owner, $repo);
    }

    public function fetchIssues(string $owner, string $repo, ?string $cursor = null): array
    {
        return $this->issues->fetchIssues($owner, $repo, $cursor);
    }

    public function fetchPullRequestCount(string $owner, string $repo): PullRequestCounts
    {
        return $this->pullRequests->fetchPullRequestCount($owner, $repo);
    }

    public function fetchPullRequests(string $owner, string $repo, ?string $cursor = null): array
    {
        return $this->pullRequests->fetchPullRequests($owner, $repo, $cursor);
    }

    public function fetchInteractionsForIssue(string $owner, string $repo, int $issueNumber): array
    {
        return $this->interactions->fetchInteractionsForIssue($owner, $repo, $issueNumber);
    }

    public function fetchIssuesPaged(string $owner, string $repo, ?string $cursor = null): array
    {
        return $this->issues->fetchIssuesPaged($owner, $repo, $cursor);
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
        return $this->issues->fetchIssuesWithInteractions($owner, $repo, $cursor);
    }

    /**
     * Extract interactions from an issue node that includes inline comments and timeline items.
     */
    public function extractInteractionsFromIssue(array $issue): array
    {
        return $this->interactions->extractInteractionsFromIssue($issue);
    }

    /**
     * Fetch issues with their timeline events in a single query.
     * This eliminates N+1 API calls when syncing events.
     */
    public function fetchIssuesWithEvents(string $owner, string $repo, ?string $cursor = null): array
    {
        return $this->issues->fetchIssuesWithEvents($owner, $repo, $cursor);
    }

    /**
     * Extract events from an issue node that includes inline timeline items.
     */
    public function extractEventsFromIssue(array $issue): array
    {
        return $this->interactions->extractEventsFromIssue($issue);
    }

    public function fetchEventsForIssue(string $owner, string $repo, int $number): array
    {
        return $this->interactions->fetchEventsForIssue($owner, $repo, $number);
    }

    public function getRateLimit(): array
    {
        return $this->connection->getRateLimit();
    }

    public function createLabel(string $owner, string $repo, string $label): int
    {
        return $this->labels->createLabel($owner, $repo, $label);
    }

    public function renameLabel(string $owner, string $repo, string $oldName, string $newName): int
    {
        return $this->labels->renameLabel($owner, $repo, $oldName, $newName);
    }

    public function getLastLabelOperationError(): ?array
    {
        return $this->labels->getLastOperationError();
    }
}
