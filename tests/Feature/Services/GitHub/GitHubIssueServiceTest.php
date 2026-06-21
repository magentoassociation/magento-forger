<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\DataTransferObjects\GitHub\IssueCounts;
use App\Exceptions\GitHubGraphQLException;
use App\Services\GitHub\GitHubConnection;
use App\Services\GitHub\GitHubIssueService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubIssueServiceTest extends TestCase
{
    private function createService(MockHandler $mock): GitHubIssueService
    {
        config()->set('github.token', 'test-token');

        return new GitHubIssueService(
            new GitHubConnection(graphQlHandler: HandlerStack::create($mock))
        );
    }

    // -------------------------------------------------------------------------
    // fetchIssueCount
    // -------------------------------------------------------------------------

    public function test_fetch_issue_count_returns_issue_counts_dto(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issues' => ['totalCount' => 30],
                        'openIssues' => ['totalCount' => 20],
                        'closedIssues' => ['totalCount' => 10],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssueCount('owner', 'repo');

        $this->assertInstanceOf(IssueCounts::class, $result);
        $this->assertSame(30, $result->total);
        $this->assertSame(20, $result->open);
        $this->assertSame(10, $result->closed);
    }

    public function test_fetch_issue_count_returns_zeros_when_repository_data_is_missing(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssueCount('owner', 'repo');

        $this->assertInstanceOf(IssueCounts::class, $result);
        $this->assertSame(0, $result->total);
        $this->assertSame(0, $result->open);
        $this->assertSame(0, $result->closed);
    }

    public function test_fetch_issue_count_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Could not resolve repository']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createService($mock)->fetchIssueCount('owner', 'repo');
    }

    // -------------------------------------------------------------------------
    // fetchIssues
    // -------------------------------------------------------------------------

    public function test_fetch_issues_returns_issues_with_rate_limit(): void
    {
        $nodes = [
            ['number' => 1, 'title' => 'Bug report'],
            ['number' => 2, 'title' => 'Feature request'],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 5000],
                    'repository' => [
                        'issues' => [
                            'nodes' => $nodes,
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssues('owner', 'repo');

        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('rateLimit', $result);
        $this->assertSame($nodes, $result['nodes']);
        $this->assertSame(['remaining' => 5000], $result['rateLimit']);
    }

    public function test_fetch_issues_returns_empty_nodes_when_no_issues(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issues' => [
                            'nodes' => [],
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssues('owner', 'repo');

        $this->assertSame([], $result['nodes']);
        $this->assertNull($result['rateLimit']);
    }

    public function test_fetch_issues_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Something went wrong']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createService($mock)->fetchIssues('owner', 'repo');
    }

    // -------------------------------------------------------------------------
    // fetchIssuesWithInteractions
    // -------------------------------------------------------------------------

    public function test_fetch_issues_with_interactions_returns_nodes_page_info_and_total(): void
    {
        $nodes = [
            ['number' => 1, 'title' => 'Bug', 'comments' => ['nodes' => []]],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 4000],
                    'repository' => [
                        'issues' => [
                            'totalCount' => 1,
                            'nodes' => $nodes,
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithInteractions('owner', 'repo');

        $this->assertSame($nodes, $result['nodes']);
        $this->assertSame(1, $result['totalCount']);
        $this->assertFalse($result['pageInfo']['hasNextPage']);
        $this->assertSame(['remaining' => 4000], $result['rateLimit']);
    }

    public function test_fetch_issues_with_interactions_returns_empty_nodes_when_no_issues(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issues' => [
                            'totalCount' => 0,
                            'nodes' => [],
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithInteractions('owner', 'repo');

        $this->assertSame([], $result['nodes']);
        $this->assertSame(0, $result['totalCount']);
    }

    // -------------------------------------------------------------------------
    // fetchIssuesWithEvents
    // -------------------------------------------------------------------------

    public function test_fetch_issues_with_events_returns_nodes_page_info_and_total(): void
    {
        $nodes = [
            ['number' => 1, 'updatedAt' => '2026-01-01T00:00:00Z', 'timelineItems' => ['nodes' => []]],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit' => ['remaining' => 4000],
                    'repository' => [
                        'issues' => [
                            'totalCount' => 1,
                            'nodes' => $nodes,
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithEvents('owner', 'repo');

        $this->assertSame($nodes, $result['nodes']);
        $this->assertSame(1, $result['totalCount']);
        $this->assertFalse($result['pageInfo']['hasNextPage']);
        $this->assertSame(['remaining' => 4000], $result['rateLimit']);
    }

    public function test_fetch_issues_with_events_returns_empty_nodes_when_no_issues(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issues' => [
                            'totalCount' => 0,
                            'nodes' => [],
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithEvents('owner', 'repo');

        $this->assertSame([], $result['nodes']);
        $this->assertSame(0, $result['totalCount']);
    }
}
