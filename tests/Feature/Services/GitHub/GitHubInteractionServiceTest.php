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
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubInteractionServiceTest extends TestCase
{
    /**
     * Build a service backed by a GraphQL mock (MockHandler drives the GraphQL client).
     */
    private function createGraphQlService(MockHandler $mock): GitHubInteractionService
    {
        config()->set('github.token', 'test-token');

        return new GitHubInteractionService(
            new GitHubConnection(graphQlHandler: HandlerStack::create($mock))
        );
    }

    /**
     * Build a service backed by a REST mock (MockHandler drives the REST client).
     */
    private function createRestService(MockHandler $mock): GitHubInteractionService
    {
        config()->set('github.token', 'test-token');

        $restClient = new Client(['handler' => HandlerStack::create($mock)]);
        $connection = new GitHubConnection(restClient: $restClient);

        return new GitHubInteractionService($connection);
    }

    // -------------------------------------------------------------------------
    // fetchInteractionsForIssue — GraphQL
    // -------------------------------------------------------------------------

    public function test_fetch_interactions_for_issue_returns_created_issue_interaction(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'Issue',
                            'author'     => ['login' => 'alice'],
                            'createdAt'  => '2024-01-10T10:00:00Z',
                            'comments'   => ['nodes' => []],
                            'timelineItems' => ['nodes' => []],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 42);

        $this->assertCount(1, $result);
        $this->assertSame('alice', $result[0]['author']);
        $this->assertSame('created_issue', $result[0]['type']);
        $this->assertSame('2024-01-10T10:00:00Z', $result[0]['date']);
    }

    public function test_fetch_interactions_for_issue_returns_created_pr_interaction(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'PullRequest',
                            'author'     => ['login' => 'bob'],
                            'createdAt'  => '2024-02-01T09:00:00Z',
                            'updatedAt'  => '2024-02-01T09:00:00Z',
                            'mergedAt'   => null,
                            'comments'   => ['nodes' => []],
                            'timelineItems' => ['nodes' => []],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 10);

        $this->assertCount(1, $result);
        $this->assertSame('bob', $result[0]['author']);
        $this->assertSame('created_pr', $result[0]['type']);
    }

    public function test_fetch_interactions_for_pr_adds_updated_pr_when_updated_at_differs(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'PullRequest',
                            'author'     => ['login' => 'carol'],
                            'createdAt'  => '2024-03-01T08:00:00Z',
                            'updatedAt'  => '2024-03-05T12:00:00Z',
                            'mergedAt'   => null,
                            'comments'   => ['nodes' => []],
                            'timelineItems' => ['nodes' => []],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 99);

        $types = array_column($result, 'type');
        $this->assertContains('created_pr', $types);
        $this->assertContains('updated_pr', $types);
    }

    public function test_fetch_interactions_for_pr_adds_merged_pr_when_merged_at_is_set(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'PullRequest',
                            'author'     => ['login' => 'dave'],
                            'createdAt'  => '2024-04-01T07:00:00Z',
                            'updatedAt'  => '2024-04-03T11:00:00Z',
                            'mergedAt'   => '2024-04-03T14:00:00Z',
                            'comments'   => ['nodes' => []],
                            'timelineItems' => ['nodes' => []],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 77);

        $types = array_column($result, 'type');
        $this->assertContains('created_pr', $types);
        $this->assertContains('updated_pr', $types);
        $this->assertContains('merged_pr', $types);
    }

    public function test_fetch_interactions_for_issue_includes_comments(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'Issue',
                            'author'     => ['login' => 'eve'],
                            'createdAt'  => '2024-05-01T06:00:00Z',
                            'comments'   => [
                                'nodes' => [
                                    ['author' => ['login' => 'frank'], 'createdAt' => '2024-05-02T10:00:00Z'],
                                    ['author' => ['login' => 'grace'], 'createdAt' => '2024-05-03T11:00:00Z'],
                                ],
                            ],
                            'timelineItems' => ['nodes' => []],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 5);

        $this->assertCount(3, $result);
        $types = array_column($result, 'type');
        $this->assertContains('created_issue', $types);
        $this->assertSame(['comment', 'comment'], array_values(array_filter($types, fn ($t) => $t === 'comment')));
    }

    public function test_fetch_interactions_for_issue_includes_timeline_events(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issueOrPullRequest' => [
                            '__typename' => 'Issue',
                            'author'     => ['login' => 'hank'],
                            'createdAt'  => '2024-06-01T05:00:00Z',
                            'comments'   => ['nodes' => []],
                            'timelineItems' => [
                                'nodes' => [
                                    ['__typename' => 'LabeledEvent', 'actor' => ['login' => 'ivy'], 'createdAt' => '2024-06-02T09:00:00Z'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 3);

        $types = array_column($result, 'type');
        $this->assertContains('labeled', $types);
    }

    public function test_fetch_interactions_for_issue_returns_empty_when_node_is_null(): void
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

        $result = $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 999);

        $this->assertSame([], $result);
    }

    public function test_fetch_interactions_for_issue_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Not found']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createGraphQlService($mock)->fetchInteractionsForIssue('owner', 'repo', 1);
    }

    // -------------------------------------------------------------------------
    // extractInteractionsFromIssue — pure method (no HTTP)
    // -------------------------------------------------------------------------

    public function test_extract_interactions_from_issue_returns_created_issue_and_comments(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubInteractionService(new GitHubConnection(restClient: $restClient));

        $issue = [
            'author'    => ['login' => 'jack'],
            'createdAt' => '2024-07-01T08:00:00Z',
            'comments'  => [
                'nodes' => [
                    ['author' => ['login' => 'kate'], 'createdAt' => '2024-07-02T09:00:00Z'],
                ],
            ],
            'timelineItems' => ['nodes' => []],
        ];

        $result = $service->extractInteractionsFromIssue($issue);

        $this->assertCount(2, $result);
        $this->assertSame('created_issue', $result[0]['type']);
        $this->assertSame('jack', $result[0]['author']);
        $this->assertSame('comment', $result[1]['type']);
        $this->assertSame('kate', $result[1]['author']);
    }

    public function test_extract_interactions_from_issue_returns_empty_when_no_created_at(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubInteractionService(new GitHubConnection(restClient: $restClient));

        $issue = [
            'author'        => ['login' => 'leo'],
            'comments'      => ['nodes' => []],
            'timelineItems' => ['nodes' => []],
        ];

        $result = $service->extractInteractionsFromIssue($issue);

        $this->assertSame([], $result);
    }

    public function test_extract_interactions_from_issue_uses_unknown_author_fallback(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubInteractionService(new GitHubConnection(restClient: $restClient));

        $issue = [
            'createdAt'     => '2024-08-01T10:00:00Z',
            'comments'      => ['nodes' => []],
            'timelineItems' => ['nodes' => []],
        ];

        $result = $service->extractInteractionsFromIssue($issue);

        $this->assertCount(1, $result);
        $this->assertSame('unknown', $result[0]['author']);
    }

    // -------------------------------------------------------------------------
    // extractEventsFromIssue — pure method (no HTTP)
    // -------------------------------------------------------------------------

    public function test_extract_events_from_issue_strips_event_suffix_and_lowercases(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubInteractionService(new GitHubConnection(restClient: $restClient));

        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'mike'], 'createdAt' => '2024-09-01T12:00:00Z'],
                    ['__typename' => 'ReopenedEvent', 'actor' => ['login' => 'nina'], 'createdAt' => '2024-09-02T13:00:00Z'],
                ],
            ],
        ];

        $result = $service->extractEventsFromIssue($issue);

        $this->assertCount(2, $result);
        $this->assertSame('closed', $result[0]['type']);
        $this->assertSame('mike', $result[0]['actor']);
        $this->assertSame('2024-09-01T12:00:00Z', $result[0]['created_at']);
        $this->assertSame('reopened', $result[1]['type']);
    }

    public function test_extract_events_from_issue_skips_nodes_without_created_at(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubInteractionService(new GitHubConnection(restClient: $restClient));

        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'LabeledEvent', 'actor' => ['login' => 'oscar']],
                    ['__typename' => 'UnlabeledEvent', 'actor' => ['login' => 'pat'], 'createdAt' => '2024-09-03T14:00:00Z'],
                ],
            ],
        ];

        $result = $service->extractEventsFromIssue($issue);

        $this->assertCount(1, $result);
        $this->assertSame('unlabeled', $result[0]['type']);
    }

    public function test_extract_events_from_issue_returns_empty_when_no_timeline_items(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubInteractionService(new GitHubConnection(restClient: $restClient));

        $result = $service->extractEventsFromIssue(['timelineItems' => ['nodes' => []]]);

        $this->assertSame([], $result);
    }

    public function test_extract_events_from_issue_uses_unknown_actor_fallback(): void
    {
        config()->set('github.token', 'test-token');
        $restClient = new Client(['handler' => HandlerStack::create(new MockHandler([]))]);
        $service = new GitHubInteractionService(new GitHubConnection(restClient: $restClient));

        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'AssignedEvent', 'createdAt' => '2024-09-10T10:00:00Z'],
                ],
            ],
        ];

        $result = $service->extractEventsFromIssue($issue);

        $this->assertCount(1, $result);
        $this->assertSame('unknown', $result[0]['actor']);
    }

    // -------------------------------------------------------------------------
    // fetchEventsForIssue — REST
    // -------------------------------------------------------------------------

    public function test_fetch_events_for_issue_returns_mapped_events(): void
    {
        $raw = [
            ['event' => 'labeled',   'actor' => ['login' => 'quinn'], 'created_at' => '2024-10-01T10:00:00Z'],
            ['event' => 'assigned',  'actor' => ['login' => 'ruth'],  'created_at' => '2024-10-02T11:00:00Z'],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($raw, JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createRestService($mock)->fetchEventsForIssue('owner', 'repo', 50);

        $this->assertCount(2, $result);
        $this->assertSame('labeled',              $result[0]['type']);
        $this->assertSame('quinn',                $result[0]['actor']);
        $this->assertSame('2024-10-01T10:00:00Z', $result[0]['created_at']);
        $this->assertSame('assigned',             $result[1]['type']);
    }

    public function test_fetch_events_for_issue_returns_empty_when_response_is_empty(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createRestService($mock)->fetchEventsForIssue('owner', 'repo', 51);

        $this->assertSame([], $result);
    }

    public function test_fetch_events_for_issue_skips_entries_missing_event_or_created_at(): void
    {
        $raw = [
            ['actor' => ['login' => 'sam']],                                  // missing 'event' and 'created_at'
            ['event' => 'closed', 'actor' => ['login' => 'tina']],            // missing 'created_at'
            ['event' => 'labeled', 'created_at' => '2024-10-05T08:00:00Z'],   // missing 'actor' → falls back to 'unknown'
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($raw, JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createRestService($mock)->fetchEventsForIssue('owner', 'repo', 52);

        $this->assertCount(1, $result);
        $this->assertSame('labeled', $result[0]['type']);
        $this->assertSame('unknown', $result[0]['actor']);
    }

    public function test_fetch_events_for_issue_returns_empty_on_rest_exception(): void
    {
        $mock = new MockHandler([
            new Response(500, [], json_encode(['message' => 'Internal Server Error'], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createRestService($mock)->fetchEventsForIssue('owner', 'repo', 53);

        $this->assertSame([], $result);
    }
}