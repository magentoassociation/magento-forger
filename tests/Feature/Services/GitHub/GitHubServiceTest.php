<?php
/*
 * @author Laura Folco <me@laurafolco.com> 2026
 */

declare(strict_types=1);

namespace Tests\Feature\Services\GitHub;

use App\DataTransferObjects\GitHub\PullRequestCounts;
use App\Exceptions\GitHubGraphQLException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Tests\Feature\Services\GitHub\Mocks\GitHubServiceMockFactory;
use Tests\TestCase;

class GitHubServiceTest extends TestCase
{
    private function createMockResponse(array $data): string
    {
        return json_encode(['data' => $data], JSON_THROW_ON_ERROR);
    }

    /**
     * @throws GitHubGraphQLException
     * @throws GuzzleException
     * @throws \JsonException
     */
    public function testFetchIssueCountReturnsCount(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->createMockResponse([
                'repository' => [
                    'issues' => ['totalCount' => 150],
                    'openIssues' => ['totalCount' => 45],
                    'closedIssues' => ['totalCount' => 105],
                ],
            ])),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssueCount('laravel', 'framework');

        $this->assertEquals(150, $result->total);
        $this->assertEquals(45, $result->open);
        $this->assertEquals(105, $result->closed);
    }

    public function testFetchIssuesUsesPagination(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->createMockResponse([
                'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                'repository' => [
                    'issues' => [
                        'nodes' => [
                            [
                                'id' => 'MDU6SXNzdWUx',
                                'number' => 1,
                                'title' => 'First issue',
                                'url' => 'https://github.com/laravel/framework/issues/1',
                                'state' => 'OPEN',
                                'createdAt' => '2024-01-01T00:00:00Z',
                                'updatedAt' => '2024-01-02T00:00:00Z',
                                'closedAt' => null,
                                'author' => ['login' => 'user1'],
                                'comments' => ['totalCount' => 5],
                                'labels' => ['nodes' => [['name' => 'bug']]],
                            ],
                        ],
                        'pageInfo' => [
                            'hasNextPage' => true,
                            'endCursor' => 'Y3Vyc29yOnYyOpK5MQ==',
                        ],
                    ],
                ],
            ])),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssues('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertArrayHasKey('rateLimit', $result);
        $this->assertCount(1, $result['nodes']);
        $this->assertEquals(1, $result['nodes'][0]['number']);
    }

    public function testFetchPullRequestCountReturnsCount(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->createMockResponse([
                'repository' => [
                    'pullRequests' => ['totalCount' => 200],
                    'openPullRequests' => ['totalCount' => 30],
                    'mergedPullRequests' => ['totalCount' => 160],
                    'closedPullRequests' => ['totalCount' => 10],
                ],
            ])),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchPullRequestCount('laravel', 'framework');

        $this->assertInstanceOf(PullRequestCounts::class, $result);
        $this->assertEquals(200, $result->total);
        $this->assertEquals(30, $result->open);
        $this->assertEquals(160, $result->merged);
        $this->assertEquals(10, $result->closed);
    }

    public function testFetchPullRequestsUsesPagination(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->createMockResponse([
                'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                'repository' => [
                    'pullRequests' => [
                        'nodes' => [
                            [
                                'id' => 'MDExOlB1bGxSZXF1ZXN0MQ==',
                                'number' => 1,
                                'title' => 'Add feature',
                                'url' => 'https://github.com/laravel/framework/pull/1',
                                'state' => 'MERGED',
                                'isDraft' => false,
                                'createdAt' => '2024-01-01T00:00:00Z',
                                'updatedAt' => '2024-01-02T00:00:00Z',
                                'mergedAt' => '2024-01-03T00:00:00Z',
                                'closedAt' => null,
                                'author' => ['login' => 'contributor1'],
                                'comments' => ['totalCount' => 3],
                                'reviews' => ['totalCount' => 2],
                                'labels' => ['nodes' => [['name' => 'enhancement']]],
                            ],
                        ],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                    ],
                ],
            ])),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchPullRequests('laravel', 'framework');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('nodes', $result);
        $this->assertCount(1, $result['nodes']);
        $this->assertEquals('MERGED', $result['nodes'][0]['state']);
    }

    public function testFetchInteractionsForIssue(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->createMockResponse([
                'repository' => [
                    'issueOrPullRequest' => [
                        '__typename' => 'Issue',
                        'author' => ['login' => 'user1'],
                        'createdAt' => '2024-01-01T00:00:00Z',
                        'comments' => [
                            'nodes' => [
                                [
                                    'author' => ['login' => 'user2'],
                                    'createdAt' => '2024-01-02T00:00:00Z',
                                ],
                            ],
                        ],
                        'timelineItems' => [
                            'nodes' => [
                                [
                                    '__typename' => 'AssignedEvent',
                                    'actor' => ['login' => 'user3'],
                                    'createdAt' => '2024-01-03T00:00:00Z',
                                ],
                            ],
                        ],
                    ],
                ],
            ])),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchInteractionsForIssue('laravel', 'framework', 1);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('created_issue', $result[0]['type']);
        $this->assertEquals('comment', $result[1]['type']);
        $this->assertEquals('assigned', $result[2]['type']);
    }

    public function testFetchIssuesWithInteractions(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->createMockResponse([
                'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                'repository' => [
                    'issues' => [
                        'nodes' => [
                            [
                                'id' => 'MDU6SXNzdWUx',
                                'number' => 1,
                                'title' => 'Issue with interactions',
                                'author' => ['login' => 'user1'],
                                'createdAt' => '2024-01-01T00:00:00Z',
                                'comments' => [
                                    'nodes' => [
                                        [
                                            'author' => ['login' => 'user2'],
                                            'createdAt' => '2024-01-02T12:00:00Z',
                                        ],
                                    ],
                                ],
                                'timelineItems' => [
                                    'nodes' => [
                                        [
                                            '__typename' => 'LabeledEvent',
                                            'actor' => ['login' => 'user3'],
                                            'createdAt' => '2024-01-02T13:00:00Z',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'totalCount' => 1,
                    ],
                ],
            ])),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssuesWithInteractions('laravel', 'framework');

        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertArrayHasKey('totalCount', $result);
        $this->assertArrayHasKey('rateLimit', $result);
        $this->assertCount(1, $result['nodes']);
    }

    public function testExtractInteractionsFromIssue(): void
    {
        $issue = [
            'number' => 1,
            'author' => ['login' => 'user1'],
            'createdAt' => '2024-01-01T00:00:00Z',
            'comments' => [
                'nodes' => [
                    [
                        'author' => ['login' => 'user2'],
                        'createdAt' => '2024-01-02T00:00:00Z',
                    ],
                ],
            ],
            'timelineItems' => [
                'nodes' => [
                    [
                        '__typename' => 'ClosedEvent',
                        'actor' => ['login' => 'user3'],
                        'createdAt' => '2024-01-03T00:00:00Z',
                    ],
                ],
            ],
        ];

        $mock = new MockHandler([new Response(200)]);
        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->extractInteractionsFromIssue($issue);

        $this->assertCount(3, $result);
        $this->assertEquals('created_issue', $result[0]['type']);
        $this->assertEquals('comment', $result[1]['type']);
        $this->assertEquals('closed', $result[2]['type']);
    }

    public function testFetchIssuesWithEvents(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->createMockResponse([
                'rateLimit' => ['remaining' => 5000, 'resetAt' => date('c', time() + 3600)],
                'repository' => [
                    'issues' => [
                        'nodes' => [
                            [
                                'number' => 1,
                                'timelineItems' => [
                                    'nodes' => [
                                        [
                                            '__typename' => 'AssignedEvent',
                                            'actor' => ['login' => 'user1'],
                                            'createdAt' => '2024-01-01T00:00:00Z',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
                        'totalCount' => 1,
                    ],
                ],
            ])),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->fetchIssuesWithEvents('laravel', 'framework');

        $this->assertArrayHasKey('nodes', $result);
        $this->assertArrayHasKey('pageInfo', $result);
        $this->assertArrayHasKey('totalCount', $result);
        $this->assertCount(1, $result['nodes']);
    }

    public function testExtractEventsFromIssue(): void
    {
        $issue = [
            'timelineItems' => [
                'nodes' => [
                    [
                        '__typename' => 'AssignedEvent',
                        'actor' => ['login' => 'user1'],
                        'createdAt' => '2024-01-01T00:00:00Z',
                    ],
                    [
                        '__typename' => 'UnlabeledEvent',
                        'actor' => ['login' => 'user2'],
                        'createdAt' => '2024-01-02T00:00:00Z',
                    ],
                ],
            ],
        ];

        $mock = new MockHandler([new Response(200)]);
        $service = GitHubServiceMockFactory::create($mock);
        $result = $service->extractEventsFromIssue($issue);

        $this->assertCount(2, $result);
        $this->assertEquals('assigned', $result[0]['type']);
        $this->assertEquals('unlabeled', $result[1]['type']);
    }

    public function testFetchIssueCountThrowsExceptionOnErrors(): void
    {
        $this->expectException(GitHubGraphQLException::class);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'data' => null,
                'errors' => [
                    ['message' => 'Bad credentials'],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $service = GitHubServiceMockFactory::create($mock);
        $service->fetchIssueCount('laravel', 'framework');
    }
}

