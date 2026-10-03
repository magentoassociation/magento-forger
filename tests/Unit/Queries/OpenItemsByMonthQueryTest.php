<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Queries;

use App\Queries\Dashboard\OpenItemsByMonthQuery;
use App\Services\Search\OpenSearchService;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class OpenItemsByMonthQueryTest extends TestCase
{
    /**
     * Run the query against a mocked client returning one bucket per given year
     * (each with a single March bucket of $count items).
     *
     * @param  array<int, int>  $years  year => open count
     * @return array<int|string, array{year: string, total: int, months: array<string, mixed>}>
     */
    private function fetch(array $years): array
    {
        $buckets = [];
        foreach ($years as $year => $count) {
            $buckets[] = [
                'key_as_string' => (string) $year,
                'doc_count' => $count,
                'by_month' => ['buckets' => [['key_as_string' => '03', 'doc_count' => $count]]],
            ];
        }

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturn(['aggregations' => ['by_year' => ['buckets' => $buckets]]]);

        return (new OpenItemsByMonthQuery($client))->execute(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX);
    }

    public function testEveryYearBackToTheOldestOpenItemGetsABlock(): void
    {
        $current = (int) date('Y');

        $result = $this->fetch([$current - 1 => 5, $current - 4 => 2]);

        $this->assertSame(range($current, $current - 4), array_keys($result));
        $this->assertSame(0, $result[$current]['total']);
        $this->assertSame(0, $result[$current - 2]['total']);
        $this->assertSame(5, $result[$current - 1]['total']);
        $this->assertSame(5, $result[$current - 1]['months']['03']['total']);
        $this->assertCount(12, $result[$current - 3]['months']);
    }

    public function testNothingOpenRendersNoYears(): void
    {
        $this->assertSame([], $this->fetch([]));
    }
}
