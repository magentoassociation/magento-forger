<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\Exceptions\GitHubGraphQLException;
use App\Services\GitHub\GitHubConnection;
use App\Services\GitHub\GitHubInteractionService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubInteractionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('github.token', 'test-token');
    }

    private function createService(MockHandler $mock): GitHubInteractionService
    {
        return new GitHubInteractionService(
            new GitHubConnection(graphQlHandler: HandlerStack::create($mock))
        );
    }

    private function emptyService(): GitHubInteractionService
    {
        return $this->createService(new MockHandler([]));
    }

    // -------------------------------------------------------------------------
    // extractInteractionsFromIssue
    // -------------------------------------------------------------------------

    public function test_extract_interactions_includes_created_issue_event(): void
    {
        $issue = [
            'author' => ['login' => 'alice'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => ['nodes' => []],
            'timelineItems' => ['nodes' => []],
        ];

        $result = $this->emptyService()->extractInteractionsFromIssue($issue);

        $this->assertCount(1, $result);
        $this->assertSame('created_issue', $result[0]['type']);
        $this->assertSame('alice', $result[0]['author']);
        $this->assertSame('2026-01-01T00:00:00Z', $result[0]['date']);
    }

    public function test_extract_interactions_includes_comments(): void
    {
        $issue = [
            'author' => ['login' => 'alice'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => [
                'nodes' => [
                    ['author' => ['login' => 'bob'], 'createdAt' => '2026-01-02T00:00:00Z'],
                    ['author' => ['login' => 'carol'], 'createdAt' => '2026-01-03T00:00:00Z'],
                ],
            ],
            'timelineItems' => ['nodes' => []],
        ];

        $result = $this->emptyService()->extractInteractionsFromIssue($issue);

        $this->assertCount(3, $result);
        $this->assertSame('comment', $result[1]['type']);
        $this->assertSame('bob', $result[1]['author']);
        $this->assertSame('comment', $result[2]['type']);
        $this->assertSame('carol', $result[2]['author']);
    }

    public function test_extract_interactions_includes_timeline_events(): void
    {
        $issue = [
            'author' => ['login' => 'alice'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => ['nodes' => []],
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'LabeledEvent', 'actor' => ['login' => 'mod'], 'createdAt' => '2026-01-02T00:00:00Z'],
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'mod'], 'createdAt' => '2026-01-03T00:00:00Z'],
                ],
            ],
        ];

        $result = $this->emptyService()->extractInteractionsFromIssue($issue);

        $this->assertCount(3, $result);
        $this->assertSame('labeled', $result[1]['type']);
        $this->assertSame('closed', $result[2]['type']);
    }

    public function test_extract_interactions_defaults_unknown_author_to_unknown(): void
    {
        $issue = [
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => ['nodes' => []],
            'timelineItems' => ['nodes' => []],
        ];

        $result = $this->emptyService()->extractInteractionsFromIssue($issue);

        $this->assertSame('unknown', $result[0]['author']);
    }

    public function test_extract_interactions_skips_created_issue_when_no_created_at(): void
    {
        $issue = [
            'author' => ['login' => 'alice'],
            'comments' => ['nodes' => []],
            'timelineItems' => ['nodes' => []],
        ];

        $result = $this->emptyService()->extractInteractionsFromIssue($issue);

        $this->assertCount(0, $result);
    }

    // -------------------------------------------------------------------------
    // extractEventsFromIssue
    // -------------------------------------------------------------------------

    public function test_extract_events_returns_typed_events_from_timeline(): void
    {
        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'AssignedEvent', 'actor' => ['login' => 'alice'], 'createdAt' => '2026-01-01T00:00:00Z'],
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'bob'], 'createdAt' => '2026-01-02T00:00:00Z'],
                ],
            ],
        ];

        $result = $this->emptyService()->extractEventsFromIssue($issue);

        $this->assertCount(2, $result);
        $this->assertSame('assigned', $result[0]['type']);
        $this->assertSame('alice', $result[0]['actor']);
        $this->assertSame('closed', $result[1]['type']);
        $this->assertSame('bob', $result[1]['actor']);
    }

    public function test_extract_events_strips_event_suffix_from_typename(): void
    {
        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'RenamedTitleEvent', 'actor' => ['login' => 'alice'], 'createdAt' => '2026-01-01T00:00:00Z'],
                ],
            ],
        ];

        $result = $this->emptyService()->extractEventsFromIssue($issue);

        $this->assertSame('renamedtitle', $result[0]['type']);
    }

    public function test_extract_events_skips_nodes_without_created_at(): void
    {
        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'AssignedEvent', 'actor' => ['login' => 'alice']],
                ],
            ],
        ];

        $result = $this->emptyService()->extractEventsFromIssue($issue);

        $this->assertCount(0, $result);
    }

    public function test_extract_events_returns_empty_for_no_timeline_items(): void
    {
        $result = $this->emptyService()->extractEventsFromIssue(['timelineItems' => ['nodes' => []]]);

        $this->assertSame([], $result);
    }

    public function test_extract_events_defaults_unknown_actor_to_unknown(): void
    {
        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'ClosedEvent', 'createdAt' => '2026-01-01T00:00:00Z'],
                ],
            ],
        ];

        $result = $this->emptyService()->extractEventsFromIssue($issue);

        $this->assertSame('unknown', $result[0]['actor']);
    }

    // -------------------------------------------------------------------------
    // fetchInteractionsForIssue
    // -------------------------------------------------------------------------

    public function test_fetch_interactions_for_issue_returns_created_issue_interaction(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'Issue',
                            'author' => ['login' => 'alice'],
                            'createdAt' => '2026-01-01T00:00:00Z',
                            'comments' => ['nodes' => []],
                            'timelineItems' => ['nodes' => []],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchInteractionsForIssue('owner', 'repo', 1);

        $this->assertCount(1, $result);
        $this->assertSame('created_issue', $result[0]['type']);
        $this->assertSame('alice', $result[0]['author']);
    }

    public function test_fetch_interactions_for_pull_request_includes_pr_events(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'PullRequest',
                            'author' => ['login' => 'bob'],
                            'createdAt' => '2026-01-01T00:00:00Z',
                            'updatedAt' => '2026-01-02T00:00:00Z',
                            'mergedAt' => '2026-01-02T00:00:00Z',
                            'comments' => ['nodes' => []],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchInteractionsForIssue('owner', 'repo', 42);

        $types = array_column($result, 'type');
        $this->assertContains('created_pr', $types);
        $this->assertContains('merged_pr', $types);
    }

    public function test_fetch_interactions_returns_empty_for_null_node(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => null,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchInteractionsForIssue('owner', 'repo', 999);

        $this->assertSame([], $result);
    }

    public function test_fetch_interactions_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Could not resolve repository']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createService($mock)->fetchInteractionsForIssue('owner', 'repo', 1);
    }
}
