<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Queries;

use App\Queries\Dashboard\CommunityPickCandidatesQuery;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class CommunityPickCandidatesQueryTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $request = [];

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testExcludesClosedDraftAndConfiguredLabels(): void
    {
        config(['github.community_picked.exclude_labels' => ['Release Line: 2.5', 'Project: Community Picked']]);

        $this->fetch([]);

        $bool = $this->request['body']['query']['bool'];
        $this->assertContains(['term' => ['is_open' => true]], $bool['filter']);
        $this->assertContains(['term' => ['is_draft' => true]], $bool['must_not']);
        $this->assertContains(
            ['terms' => ['labels.keyword' => ['Release Line: 2.5', 'Project: Community Picked']]],
            $bool['must_not'],
        );
        $this->assertStringEndsWith('github-pull-requests', $this->request['index']);
    }

    public function testExcludeListComesFromConfig(): void
    {
        config(['github.community_picked.exclude_labels' => ['Release Line: 2.6']]);

        $this->fetch([]);

        $this->assertContains(
            ['terms' => ['labels.keyword' => ['Release Line: 2.6']]],
            $this->request['body']['query']['bool']['must_not'],
        );
    }

    public function testRanksByThumbsUpThenOldestFirst(): void
    {
        $this->fetch([]);

        $sort = $this->request['body']['sort'];
        $this->assertSame('desc', $sort[0]['thumbs_up_count']['order']);
        $this->assertSame('long', $sort[0]['thumbs_up_count']['unmapped_type']);
        $this->assertSame(['created_at' => ['order' => 'asc']], $sort[1]);
    }

    public function testPaginatesFiftyPerPage(): void
    {
        $this->fetch([], page: 3);

        $this->assertSame(100, $this->request['body']['from']);
        $this->assertSame(50, $this->request['body']['size']);
    }

    public function testPageBelowOneIsTreatedAsFirstPage(): void
    {
        $this->fetch([], page: 0);

        $this->assertSame(0, $this->request['body']['from']);
    }

    public function testMapsHitsToRowsAndTotal(): void
    {
        $result = $this->fetch([
            ['id' => 39812, 'title' => 'Exclude orphan plugins', 'url' => 'https://github.com/magento/magento2/pull/39812',
                'author' => 'swnsma', 'created_at' => '2026-07-06T00:00:00Z', 'thumbs_up_count' => 8, 'linked_issues' => [39790]],
            ['id' => 40001, 'title' => 'Not synced yet', 'url' => 'https://github.com/magento/magento2/pull/40001',
                'author' => null, 'created_at' => '2026-08-01T00:00:00Z'],
        ], total: 120);

        $this->assertSame(120, $result['total']);
        $this->assertSame([
            'number' => 39812,
            'title' => 'Exclude orphan plugins',
            'url' => 'https://github.com/magento/magento2/pull/39812',
            'author' => 'swnsma',
            'created_at' => '2026-07-06T00:00:00Z',
            'thumbs_up_count' => 8,
            'linked_issues' => [39790],
        ], $result['rows'][0]);
        $this->assertSame(0, $result['rows'][1]['thumbs_up_count']);
        $this->assertSame([], $result['rows'][1]['linked_issues']);
    }

    /**
     * Run the query against a mocked client, capturing the request it sends.
     *
     * @param  list<array<string, mixed>>  $sources  `_source` of each hit
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    private function fetch(array $sources, int $page = 1, ?int $total = null): array
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->once()->andReturnUsing(function (array $params) use ($sources, $total): array {
            $this->request = $params;

            return ['hits' => [
                'total' => ['value' => $total ?? count($sources)],
                'hits' => array_map(static fn (array $s): array => ['_source' => $s], $sources),
            ]];
        });

        return (new CommunityPickCandidatesQuery($client))->execute($page);
    }
}
