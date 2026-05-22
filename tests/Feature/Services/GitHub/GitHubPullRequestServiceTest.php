<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\DataTransferObjects\GitHub\PullRequestCounts;
use App\Exceptions\GitHubGraphQLException;
use App\Services\GitHub\GitHubConnection;
use App\Services\GitHub\GitHubPullRequestService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class GitHubPullRequestServiceTest extends TestCase
{
    private function createService(MockHandler $mock): GitHubPullRequestService
    {
        config()->set('github.token', 'test-token');

        return new GitHubPullRequestService(
            new GitHubConnection(graphQlHandler: HandlerStack::create($mock))
        );
    }

    // -------------------------------------------------------------------------
    // fetchPullRequestCount
    // -------------------------------------------------------------------------

    public function test_fetch_pull_request_count_returns_pull_request_counts_dto(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'pullRequests'       => ['totalCount' => 20],
                        'openPullRequests'   => ['totalCount' => 5],
                        'mergedPullRequests' => ['totalCount' => 10],
                        'closedPullRequests' => ['totalCount' => 5],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchPullRequestCount('owner', 'repo');

        $this->assertInstanceOf(PullRequestCounts::class, $result);
        $this->assertSame(20, $result->total);
        $this->assertSame(5, $result->open);
        $this->assertSame(10, $result->merged);
        $this->assertSame(5, $result->closed);
    }

    public function test_fetch_pull_request_count_returns_zeros_when_repository_data_is_missing(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchPullRequestCount('owner', 'repo');

        $this->assertInstanceOf(PullRequestCounts::class, $result);
        $this->assertSame(0, $result->total);
        $this->assertSame(0, $result->open);
        $this->assertSame(0, $result->merged);
        $this->assertSame(0, $result->closed);
    }

    public function test_fetch_pull_request_count_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Repository not found']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createService($mock)->fetchPullRequestCount('owner', 'repo');
    }

    // -------------------------------------------------------------------------
    // fetchPullRequests
    // -------------------------------------------------------------------------

    public function test_fetch_pull_requests_returns_pull_requests_with_rate_limit(): void
    {
        $nodes = [
            ['number' => 101, 'title' => 'Fix typo'],
            ['number' => 102, 'title' => 'Add feature'],
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'rateLimit'  => ['remaining' => 4800],
                    'repository' => [
                        'pullRequests' => [
                            'nodes'    => $nodes,
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchPullRequests('owner', 'repo');

        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('rateLimit', $result);
        $this->assertSame($nodes, $result['nodes']);
        $this->assertSame(['remaining' => 4800], $result['rateLimit']);
    }

    public function test_fetch_pull_requests_returns_empty_when_no_pull_requests(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'pullRequests' => [
                            'nodes'    => [],
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchPullRequests('owner', 'repo');

        $this->assertSame([], $result['nodes']);
        $this->assertNull($result['rateLimit']);
    }

    public function test_fetch_pull_requests_with_cursor_returns_paginated_results(): void
    {
        $nodes = [['number' => 200, 'title' => 'Page 2 PR']];

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => [
                    'repository' => [
                        'pullRequests' => [
                            'nodes'    => $nodes,
                            'pageInfo' => ['hasNextPage' => false, 'endCursor' => 'end-cursor'],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchPullRequests('owner', 'repo', 'some-cursor');

        $this->assertSame($nodes, $result['nodes']);
        $this->assertSame('end-cursor', $result['pageInfo']['endCursor']);
    }

    public function test_fetch_pull_requests_returns_only_rate_limit_when_repository_key_absent(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => ['repository' => []],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $this->createService($mock)->fetchPullRequests('owner', 'repo');

        // Service does `$pullRequests = $data['repository']['pullRequests'] ?? []` then appends rateLimit, so result
        // contains only rateLimit when pullRequests key is absent.
        $this->assertSame(['rateLimit' => null], $result);
    }

    public function test_fetch_pull_requests_throws_graphql_exception_on_errors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'errors' => [['message' => 'Rate limit exceeded']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $this->createService($mock)->fetchPullRequests('owner', 'repo');
    }
}
