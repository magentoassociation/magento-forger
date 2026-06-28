<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\Services\Leaderboard\ClaimRecordReader;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class ClaimRecordReaderTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $hits
     * @return array<string, mixed>
     */
    private function hits(array $hits): array
    {
        // No _scroll_id → scroll() processes one batch and skips the scroll/clear calls.
        return ['hits' => ['hits' => array_map(fn (array $s): array => ['_source' => $s], $hits)]];
    }

    public function test_collapses_repeated_self_assignments_to_one_claim(): void
    {
        $timeline = OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_PR_TIMELINE_INDEX);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')
            ->andReturnUsing(function (array $params) use ($timeline): array {
                $type = $params['body']['query']['bool']['filter'][0]['term']['type.keyword'] ?? null;

                if ($params['index'] === $timeline && $type === 'ReviewRequestedEvent') {
                    // jane self-assigns PR 10 twice (a reassignment), plus one real claim on PR 11.
                    return $this->hits([
                        ['pr_number' => 10, 'actor' => 'jane', 'requested_reviewer' => 'jane', 'created_at' => '2026-03-10T00:00:00Z'],
                        ['pr_number' => 10, 'actor' => 'jane', 'requested_reviewer' => 'jane', 'created_at' => '2026-03-01T00:00:00Z'],
                        ['pr_number' => 11, 'actor' => 'jane', 'requested_reviewer' => 'jane', 'created_at' => '2026-03-05T00:00:00Z'],
                    ]);
                }

                // pendingReviewTimes (LabeledEvent) and reviewTimes (reviews index): none.
                return $this->hits([]);
            });

        $reader = new ClaimRecordReader($client);
        $records = $reader->read(Carbon::parse('2026-01-01T00:00:00Z'), Carbon::parse('2026-06-01T00:00:00Z'));

        // One record per unique (pr, maintainer): PR 10 collapsed from two, PR 11 once.
        $this->assertCount(2, $records);

        $pr10 = array_values(array_filter($records, fn ($r): bool => $r->prNumber === 10));
        $this->assertCount(1, $pr10);
        // Earliest self-assignment kept.
        $this->assertTrue($pr10[0]->claimedAt->equalTo(Carbon::parse('2026-03-01T00:00:00Z')));
    }
}
