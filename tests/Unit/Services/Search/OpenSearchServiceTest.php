<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Search;

use App\Services\Search\OpenSearchService;
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

    public function test_maps_labeled_event_with_label_name(): void
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

    public function test_maps_review_requested_event_with_reviewer(): void
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

    public function test_skips_nodes_without_id(): void
    {
        $docs = $this->mapTimeline([
            'number' => 789,
            'timelineItems' => [
                'nodes' => [
                    ['__typename' => 'LabeledEvent', 'actor' => ['login' => 'x'], 'createdAt' => '2026-01-01T00:00:00Z'],
                ],
            ],
        ]);

        $this->assertSame([], $docs);
    }

    public function test_returns_empty_for_no_timeline_items(): void
    {
        $this->assertSame([], $this->mapTimeline(['number' => 1]));
    }
}
