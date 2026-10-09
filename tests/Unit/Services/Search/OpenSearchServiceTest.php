<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\OpenSearchService;
use Mockery;
use OpenSearch\Client;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class OpenSearchServiceTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $pr
     * @return list<array{id: string, body: array<string, mixed>}>
     */
    private function mapTimeline(array $pr): array
    {
        $service = (new ReflectionClass(OpenSearchService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(OpenSearchService::class, 'toPullRequestTimelineDocuments');

        return $method->invoke($service, $pr);
    }

    public function testMapsLabeledEventWithLabelName(): void
    {
        $docs = $this->mapTimeline([
            'number' => 123,
            'timelineItems' => [
                'nodes' => [
                    [
                        '__typename' => 'LabeledEvent',
                        'id' => 'NODE_1',
                        'actor' => ['login' => 'adobe-bot'],
                        'createdAt' => '2026-01-01T00:00:00Z',
                        'label' => ['name' => 'Progress: pending review'],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $docs);
        $this->assertSame('NODE_1', $docs[0]['id']);
        $this->assertSame(123, $docs[0]['body']['pr_number']);
        $this->assertSame('LabeledEvent', $docs[0]['body']['type']);
        $this->assertSame('adobe-bot', $docs[0]['body']['actor']);
        $this->assertSame('Progress: pending review', $docs[0]['body']['label_name']);
        $this->assertNull($docs[0]['body']['requested_reviewer']);
    }

    public function testMapsReviewRequestedEventWithReviewer(): void
    {
        $docs = $this->mapTimeline([
            'number' => 456,
            'timelineItems' => [
                'nodes' => [
                    [
                        '__typename' => 'ReviewRequestedEvent',
                        'id' => 'NODE_2',
                        'actor' => ['login' => 'maintainer1'],
                        'createdAt' => '2026-02-01T00:00:00Z',
                        'requestedReviewer' => ['login' => 'maintainer1'],
                    ],
                ],
            ],
        ]);

        $this->assertSame('ReviewRequestedEvent', $docs[0]['body']['type']);
        $this->assertSame('maintainer1', $docs[0]['body']['requested_reviewer']);
        $this->assertNull($docs[0]['body']['label_name']);
    }

    public function testSkipsNodesWithoutId(): void
    {
        $docs = $this->mapTimeline([
            'number' => 789,
            'timelineItems' => [
                'nodes' => [
                    [
                        '__typename' => 'LabeledEvent',
                        'actor' => ['login' => 'x'],
                        'createdAt' => '2026-01-01T00:00:00Z',
                    ],
                ],
            ],
        ]);

        $this->assertSame([], $docs);
    }

    public function testReturnsEmptyForNoTimelineItems(): void
    {
        $this->assertSame([], $this->mapTimeline(['number' => 1]));
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function buildDocument(string $method, array $node): array
    {
        $service = (new ReflectionClass(OpenSearchService::class))->newInstanceWithoutConstructor();

        return (new ReflectionMethod(OpenSearchService::class, $method))->invoke($service, $node);
    }

    public function testPullRequestDocumentIncludesSizeAndAuthorCompany(): void
    {
        $doc = $this->buildDocument('toPullRequestDocument', [
            'number' => 10,
            'id' => 'PR_10',
            'title' => 'Fix bug',
            'url' => 'https://github.com/magento/magento2/pull/10',
            'state' => 'MERGED',
            'isDraft' => false,
            'labels' => ['nodes' => []],
            'createdAt' => '2026-01-01T00:00:00Z',
            'updatedAt' => '2026-01-02T00:00:00Z',
            'mergedAt' => '2026-01-03T00:00:00Z',
            'closedAt' => '2026-01-03T00:00:00Z',
            'author' => ['login' => 'jane', 'company' => '@AcmeCorp'],
            'additions' => 120,
            'deletions' => 30,
            'changedFiles' => 4,
            'comments' => ['totalCount' => 2],
            'reviews' => ['totalCount' => 1],
        ]);

        $this->assertSame('jane', $doc['author']);
        $this->assertSame('@AcmeCorp', $doc['author_company']);
        $this->assertSame(120, $doc['additions']);
        $this->assertSame(30, $doc['deletions']);
        $this->assertSame(4, $doc['changed_files']);
    }

    public function testPullRequestDocumentDefaultsMissingSizeAndCompanyToNull(): void
    {
        $doc = $this->buildDocument('toPullRequestDocument', [
            'number' => 11,
            'id' => 'PR_11',
            'title' => 'No stats',
            'url' => 'https://github.com/magento/magento2/pull/11',
            'state' => 'OPEN',
            'isDraft' => false,
            'labels' => ['nodes' => []],
            'createdAt' => '2026-01-01T00:00:00Z',
            'updatedAt' => '2026-01-02T00:00:00Z',
            'author' => ['login' => 'bot'],
            'comments' => ['totalCount' => 0],
            'reviews' => ['totalCount' => 0],
        ]);

        $this->assertNull($doc['author_company']);
        $this->assertNull($doc['additions']);
        $this->assertNull($doc['deletions']);
        $this->assertNull($doc['changed_files']);
        $this->assertSame(0, $doc['thumbs_up_count']);
        $this->assertSame([], $doc['linked_issues']);
    }

    public function testPullRequestDocumentIncludesThumbsUpCountAndLinkedIssues(): void
    {
        $doc = $this->buildDocument('toPullRequestDocument', [
            'number' => 12,
            'id' => 'PR_12',
            'title' => 'Fixes two issues',
            'url' => 'https://github.com/magento/magento2/pull/12',
            'state' => 'OPEN',
            'isDraft' => false,
            'labels' => ['nodes' => []],
            'createdAt' => '2026-01-01T00:00:00Z',
            'updatedAt' => '2026-01-02T00:00:00Z',
            'author' => ['login' => 'jane'],
            'comments' => ['totalCount' => 0],
            'reviews' => ['totalCount' => 0],
            'reactions' => ['totalCount' => 8],
            'closingIssuesReferences' => ['nodes' => [['number' => 101], ['number' => 102]]],
        ]);

        $this->assertSame(8, $doc['thumbs_up_count']);
        $this->assertSame([101, 102], $doc['linked_issues']);
    }

    public function testReactionsUpdateIsAPartialUpdateWithoutUpsert(): void
    {
        $sent = [];
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('bulk')->once()->andReturnUsing(function (array $params) use (&$sent): array {
            $sent = $params['body'];

            return ['errors' => false, 'items' => []];
        });
        $this->app->instance(Client::class, $client);

        (new OpenSearchService)->updatePullRequestReactions([[
            'number' => 39812,
            'isDraft' => true,
            'reactions' => ['totalCount' => 8],
            'closingIssuesReferences' => ['nodes' => [['number' => 39790]]],
        ]]);

        $this->assertSame(['update' => ['_index' => OpenSearchService::getIndexWithPrefix('github-pull-requests'), '_id' => 39812]], $sent[0]);
        $this->assertSame(['doc' => ['is_draft' => true, 'thumbs_up_count' => 8, 'linked_issues' => [39790]]], $sent[1]);
    }

    public function testReactionsUpdateToleratesPrsNotYetIndexed(): void
    {
        $this->bindBulkResponse([
            'errors' => true,
            'items' => [
                ['update' => ['_id' => '1', 'status' => 200]],
                ['update' => ['_id' => '2', 'status' => 404, 'error' => ['type' => 'document_missing_exception', 'reason' => 'document missing']]],
            ],
        ]);

        (new OpenSearchService)->updatePullRequestReactions([$this->reactionNode(1), $this->reactionNode(2)]);

        $this->addToAssertionCount(1);
    }

    public function testReactionsUpdateThrowsWhenAnyOtherItemFails(): void
    {
        $this->bindBulkResponse([
            'errors' => true,
            'items' => [
                ['update' => ['_id' => '1', 'status' => 404, 'error' => ['type' => 'document_missing_exception', 'reason' => 'document missing']]],
                ['update' => ['_id' => '2', 'status' => 400, 'error' => ['type' => 'mapper_parsing_exception', 'reason' => 'failed to parse field [thumbs_up_count]']]],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('1 PR reaction update(s) failed: #2: mapper_parsing_exception failed to parse field [thumbs_up_count]');

        (new OpenSearchService)->updatePullRequestReactions([$this->reactionNode(1), $this->reactionNode(2)]);
    }

    public function testReactionsUpdateSendsNothingForNoPrs(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('bulk');
        $this->app->instance(Client::class, $client);

        (new OpenSearchService)->updatePullRequestReactions([]);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function bindBulkResponse(array $response): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('bulk')->once()->andReturn($response);
        $this->app->instance(Client::class, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function reactionNode(int $number): array
    {
        return ['number' => $number, 'isDraft' => false, 'reactions' => ['totalCount' => 1], 'closingIssuesReferences' => ['nodes' => []]];
    }

    public function testIssueDocumentIncludesAuthorCompany(): void
    {
        $doc = $this->buildDocument('toIssueDocument', [
            'number' => 5,
            'id' => 'I_5',
            'title' => 'Broken thing',
            'url' => 'https://github.com/magento/magento2/issues/5',
            'state' => 'OPEN',
            'labels' => ['nodes' => []],
            'createdAt' => '2026-01-01T00:00:00Z',
            'updatedAt' => '2026-01-02T00:00:00Z',
            'author' => ['login' => 'kim', 'company' => 'Adobe'],
            'comments' => ['totalCount' => 3],
        ]);

        $this->assertSame('kim', $doc['author']);
        $this->assertSame('Adobe', $doc['author_company']);
    }
}
