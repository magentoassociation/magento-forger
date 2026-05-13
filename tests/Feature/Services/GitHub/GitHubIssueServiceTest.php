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
                        'issues'       => ['totalCount' => 30],
                        'openIssues'   => ['totalCount' => 20],
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
                    'rateLimit'  => ['remaining' => 5000],
                    'repository' => [
                        'issues' => [
                            'nodes'    => $nodes,
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
                            'nodes'    => [],
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
    // fetchIssuesPaged
    // -------------------------------------------------------------------------

    public function test_fetch_issues_paged_returns_issues_and_pagination(): void
    {
        $nodes = [['number' => 5, 'title' => 'Issue 5']];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issues' => [
                            'nodes'    => $nodes,
                            'pageInfo' => ['hasNextPage' => true, 'endCursor' => 'cursor-abc'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesPaged('owner', 'repo');

        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('endCursor', $result);
        $this->assertArrayHasKey('hasNextPage', $result);
        $this->assertSame($nodes, $result['issues']);
        $this->assertSame('cursor-abc', $result['endCursor']);
        $this->assertTrue($result['hasNextPage']);
    }

    public function test_fetch_issues_paged_forwards_cursor_parameter(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'issues' => [
                            'nodes'    => [],
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesPaged('owner', 'repo', 'cursor-xyz');

        $this->assertSame([], $result['issues']);
        $this->assertNull($result['endCursor']);
        $this->assertFalse($result['hasNextPage']);
    }

    public function test_fetch_issues_paged_returns_defaults_when_data_is_absent(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => ['repository' => []],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesPaged('owner', 'repo');

        $this->assertSame([], $result['issues']);
        $this->assertNull($result['endCursor']);
        $this->assertFalse($result['hasNextPage']);
    }

    // -------------------------------------------------------------------------
    // fetchIssuesWithInteractions
    // -------------------------------------------------------------------------

    public function test_fetch_issues_with_interactions_returns_expected_shape(): void
    {
        $nodes = [['number' => 1, 'comments' => ['nodes' => []]]];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit'  => ['remaining' => 4999],
                    'repository' => [
                        'issues' => [
                            'nodes'      => $nodes,
                            'pageInfo'   => ['hasNextPage' => false, 'endCursor' => null],
                            'totalCount' => 1,
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithInteractions('owner', 'repo');

        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertArrayHasKey('totalCount', $result);
        $this->assertArrayHasKey('rateLimit', $result);
        $this->assertSame($nodes, $result['nodes']);
        $this->assertSame(1, $result['totalCount']);
        $this->assertSame(['remaining' => 4999], $result['rateLimit']);
    }

    public function test_fetch_issues_with_interactions_returns_empty_defaults_when_no_issues(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => ['repository' => []],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithInteractions('owner', 'repo');

        $this->assertSame([], $result['nodes']);
        $this->assertSame([], $result['pageInfo']);
        $this->assertSame(0, $result['totalCount']);
        $this->assertNull($result['rateLimit']);
    }

    public function test_fetch_issues_with_interactions_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Unauthorized']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createService($mock)->fetchIssuesWithInteractions('owner', 'repo');
    }

    // -------------------------------------------------------------------------
    // fetchIssuesWithEvents
    // -------------------------------------------------------------------------

    public function test_fetch_issues_with_events_returns_expected_shape(): void
    {
        $nodes = [['number' => 7, 'timelineItems' => ['nodes' => []]]];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit'  => ['remaining' => 3000],
                    'repository' => [
                        'issues' => [
                            'nodes'      => $nodes,
                            'pageInfo'   => ['hasNextPage' => true, 'endCursor' => 'cursor-events'],
                            'totalCount' => 7,
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithEvents('owner', 'repo');

        $this->assertSame($nodes, $result['nodes']);
        $this->assertSame(['hasNextPage' => true, 'endCursor' => 'cursor-events'], $result['pageInfo']);
        $this->assertSame(7, $result['totalCount']);
        $this->assertSame(['remaining' => 3000], $result['rateLimit']);
    }

    public function test_fetch_issues_with_events_returns_empty_defaults_when_no_issues(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => ['repository' => []],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchIssuesWithEvents('owner', 'repo');

        $this->assertSame([], $result['nodes']);
        $this->assertSame([], $result['pageInfo']);
        $this->assertSame(0, $result['totalCount']);
        $this->assertNull($result['rateLimit']);
    }

    public function test_fetch_issues_with_events_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Field does not exist']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createService($mock)->fetchIssuesWithEvents('owner', 'repo');
    }
}