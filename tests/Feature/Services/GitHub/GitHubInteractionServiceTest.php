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
use Illuminate\Support\Facades\Log;
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

    private function createServiceWithRestMock(MockHandler $mock): GitHubInteractionService
    {
        $restClient = new Client(['handler' => HandlerStack::create($mock)]);

        return new GitHubInteractionService(
            new GitHubConnection(restClient: $restClient)
        );
    }

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

    public function test_extract_interactions_captures_label_name(): void
    {
        $issue = [
            'author' => ['login' => 'alice'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => ['nodes' => []],
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'LabeledEvent', 'actor' => ['login' => 'mod'], 'createdAt' => '2026-01-02T00:00:00Z', 'label' => ['name' => 'Progress: pending review']],
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'mod'], 'createdAt' => '2026-01-03T00:00:00Z'],
                ],
            ],
        ];

        $result = $this->emptyService()->extractInteractionsFromIssue($issue);

        $this->assertSame('Progress: pending review', $result[1]['label']);
        $this->assertNull($result[2]['label']);
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

    public function test_extract_events_captures_label_name(): void
    {
        $issue = [
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'LabeledEvent', 'actor' => ['login' => 'mod'], 'createdAt' => '2026-01-01T00:00:00Z', 'label' => ['name' => 'Progress: pending review']],
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'bob'], 'createdAt' => '2026-01-02T00:00:00Z'],
                ],
            ],
        ];

        $result = $this->emptyService()->extractEventsFromIssue($issue);

        $this->assertSame('Progress: pending review', $result[0]['label']);
        $this->assertNull($result[1]['label']);
    }

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

    public function test_fetch_events_for_issue_returns_events_from_rest_timeline(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                ['event' => 'assigned', 'actor' => ['login' => 'alice'], 'created_at' => '2026-01-01T00:00:00Z'],
                ['event' => 'closed', 'actor' => ['login' => 'bob'], 'created_at' => '2026-01-02T00:00:00Z'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createServiceWithRestMock($mock)->fetchEventsForIssue('owner', 'repo', 1);

        $this->assertCount(2, $result);
        $this->assertSame('assigned', $result[0]['type']);
        $this->assertSame('alice', $result[0]['actor']);
        $this->assertSame('2026-01-01T00:00:00Z', $result[0]['created_at']);
        $this->assertSame('closed', $result[1]['type']);
        $this->assertSame('bob', $result[1]['actor']);
    }

    public function test_fetch_events_for_issue_defaults_actor_to_unknown_when_missing(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                ['event' => 'labeled', 'created_at' => '2026-01-01T00:00:00Z'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createServiceWithRestMock($mock)->fetchEventsForIssue('owner', 'repo', 1);

        $this->assertCount(1, $result);
        $this->assertSame('unknown', $result[0]['actor']);
    }

    public function test_fetch_events_for_issue_skips_events_missing_event_field(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                ['actor' => ['login' => 'alice'], 'created_at' => '2026-01-01T00:00:00Z'],
                ['event' => 'closed', 'actor' => ['login' => 'bob'], 'created_at' => '2026-01-02T00:00:00Z'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createServiceWithRestMock($mock)->fetchEventsForIssue('owner', 'repo', 1);

        $this->assertCount(1, $result);
        $this->assertSame('closed', $result[0]['type']);
    }

    public function test_fetch_events_for_issue_skips_events_missing_created_at(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                ['event' => 'assigned', 'actor' => ['login' => 'alice']],
                ['event' => 'closed', 'actor' => ['login' => 'bob'], 'created_at' => '2026-01-02T00:00:00Z'],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createServiceWithRestMock($mock)->fetchEventsForIssue('owner', 'repo', 1);

        $this->assertCount(1, $result);
        $this->assertSame('closed', $result[0]['type']);
    }

    public function test_fetch_events_for_issue_returns_empty_for_empty_response(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createServiceWithRestMock($mock)->fetchEventsForIssue('owner', 'repo', 1);

        $this->assertSame([], $result);
    }

    public function test_fetch_events_for_issue_returns_empty_and_logs_on_api_error(): void
    {
        Log::spy();

        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $result = $this->createServiceWithRestMock($mock)->fetchEventsForIssue('owner', 'repo', 42);

        $this->assertSame([], $result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to fetch events for issue #42', \Mockery::any());
    }

    public function test_fetch_events_for_issue_returns_empty_and_logs_on_malformed_json(): void
    {
        Log::spy();

        $mock = new MockHandler([
            new Response(200, [], 'not-valid-json'),
        ]);

        $result = $this->createServiceWithRestMock($mock)->fetchEventsForIssue('owner', 'repo', 7);

        $this->assertSame([], $result);
        Log::shouldHaveReceived('error')
            ->once()
            ->with('Failed to fetch events for issue #7', \Mockery::any());
    }

    public function test_fetch_all_interactions_aggregates_paginated_comments(): void
    {
        $issue = [
            'number' => 1,
            'author' => ['login' => 'alice'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => [
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor1'],
                'nodes' => [
                    ['author' => ['login' => 'bob'], 'createdAt' => '2026-01-02T00:00:00Z'],
                ],
            ],
            'timelineItems' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issue' => [
                            'comments' => [
                                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                                'nodes' => [
                                    ['author' => ['login' => 'carol'], 'createdAt' => '2026-01-03T00:00:00Z'],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchAllInteractionsFromIssue($issue, 'owner', 'repo');

        $this->assertCount(3, $result);
        $types = array_column($result, 'type');
        $this->assertSame(['created_issue', 'comment', 'comment'], $types);
        $authors = array_column($result, 'author');
        $this->assertContains('bob', $authors);
        $this->assertContains('carol', $authors);
    }

    public function test_fetch_all_interactions_aggregates_paginated_timeline_items(): void
    {
        $issue = [
            'number' => 2,
            'author' => ['login' => 'alice'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []],
            'timelineItems' => [
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'tl-cursor1'],
                'nodes' => [
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'mod'], 'createdAt' => '2026-01-02T00:00:00Z', 'label' => null],
                ],
            ],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issue' => [
                            'timelineItems' => [
                                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                                'nodes' => [
                                    ['__typename' => 'AssignedEvent', 'actor' => ['login' => 'admin'], 'createdAt' => '2026-01-03T00:00:00Z', 'label' => null],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchAllInteractionsFromIssue($issue, 'owner', 'repo');

        $this->assertCount(3, $result);
        $types = array_column($result, 'type');
        $this->assertContains('created_issue', $types);
        $this->assertContains('closed', $types);
        $this->assertContains('assigned', $types);
    }

    public function test_fetch_all_interactions_stops_comments_pagination_on_null_response(): void
    {
        $issue = [
            'number' => 3,
            'author' => ['login' => 'alice'],
            'createdAt' => '2026-01-01T00:00:00Z',
            'comments' => [
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor1'],
                'nodes' => [
                    ['author' => ['login' => 'bob'], 'createdAt' => '2026-01-02T00:00:00Z'],
                ],
            ],
            'timelineItems' => ['pageInfo' => ['hasNextPage' => false], 'nodes' => []],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => null], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchAllInteractionsFromIssue($issue, 'owner', 'repo');

        $this->assertCount(2, $result);
        $this->assertSame('created_issue', $result[0]['type']);
        $this->assertSame('comment', $result[1]['type']);
    }

    public function test_fetch_all_events_aggregates_paginated_timeline_items(): void
    {
        $issue = [
            'number' => 10,
            'timelineItems' => [
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'ev-cursor1'],
                'nodes' => [
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'alice'], 'createdAt' => '2026-01-01T00:00:00Z', 'label' => null],
                ],
            ],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issue' => [
                            'timelineItems' => [
                                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                                'nodes' => [
                                    ['__typename' => 'AssignedEvent', 'actor' => ['login' => 'bob'], 'createdAt' => '2026-01-02T00:00:00Z', 'label' => null],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchAllEventsFromIssue($issue, 'owner', 'repo');

        $this->assertCount(2, $result);
        $this->assertSame('closed', $result[0]['type']);
        $this->assertSame('alice', $result[0]['actor']);
        $this->assertSame('assigned', $result[1]['type']);
        $this->assertSame('bob', $result[1]['actor']);
    }

    public function test_fetch_all_events_stops_pagination_on_null_response(): void
    {
        $issue = [
            'number' => 11,
            'timelineItems' => [
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'ev-cursor1'],
                'nodes' => [
                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'alice'], 'createdAt' => '2026-01-01T00:00:00Z', 'label' => null],
                ],
            ],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => null], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchAllEventsFromIssue($issue, 'owner', 'repo');

        $this->assertCount(1, $result);
        $this->assertSame('closed', $result[0]['type']);
    }

    public function test_fetch_all_events_traverses_multiple_pages(): void
    {
        $issue = [
            'number' => 12,
            'timelineItems' => [
                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'page1'],
                'nodes' => [
                    ['__typename' => 'AssignedEvent', 'actor' => ['login' => 'alice'], 'createdAt' => '2026-01-01T00:00:00Z', 'label' => null],
                ],
            ],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issue' => [
                            'timelineItems' => [
                                'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'page2'],
                                'nodes' => [
                                    ['__typename' => 'ClosedEvent', 'actor' => ['login' => 'bob'], 'createdAt' => '2026-01-02T00:00:00Z', 'label' => null],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issue' => [
                            'timelineItems' => [
                                'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                                'nodes' => [
                                    ['__typename' => 'LabeledEvent', 'actor' => ['login' => 'carol'], 'createdAt' => '2026-01-03T00:00:00Z', 'label' => null],
                                ],
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchAllEventsFromIssue($issue, 'owner', 'repo');

        $this->assertCount(3, $result);
        $this->assertSame(['assigned', 'closed', 'labeled'], array_column($result, 'type'));
    }
}
