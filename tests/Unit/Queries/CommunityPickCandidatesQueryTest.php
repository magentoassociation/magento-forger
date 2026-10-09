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
    /** @var array<string, mixed> The page search request. */
    private array $pageRequest = [];

    /** @var list<array<string, mixed>> */
    private array $issueRequests = [];

    /** @var list<array<string, mixed>> */
    private array $facetRequests = [];

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testExcludesClosedDraftAndConfiguredLabels(): void
    {
        config(['github.community_picked.exclude_labels' => ['Release Line: 2.5', 'Project: Community Picked']]);

        $this->fetch();

        $bool = $this->pageRequest['body']['query']['bool'];
        $this->assertContains(['term' => ['is_open' => true]], $bool['filter']);
        $this->assertContains(['term' => ['is_draft' => true]], $bool['must_not']);
        $this->assertContains(
            ['terms' => ['labels.keyword' => ['Release Line: 2.5', 'Project: Community Picked']]],
            $bool['must_not'],
        );
        $this->assertStringEndsWith('github-pull-requests', $this->pageRequest['index']);
    }

    public function testExcludeListComesFromConfig(): void
    {
        config(['github.community_picked.exclude_labels' => ['Release Line: 2.6']]);

        $this->fetch();

        $this->assertContains(
            ['terms' => ['labels.keyword' => ['Release Line: 2.6']]],
            $this->pageRequest['body']['query']['bool']['must_not'],
        );
    }

    public function testRanksByThumbsUpThenOldestFirst(): void
    {
        $this->fetch();

        $sort = $this->pageRequest['body']['sort'];
        $this->assertSame('desc', $sort[0]['thumbs_up_count']['order']);
        $this->assertSame('long', $sort[0]['thumbs_up_count']['unmapped_type']);
        $this->assertSame(['created_at' => ['order' => 'asc']], $sort[1]);
    }

    public function testPaginatesFiftyPerPage(): void
    {
        $this->fetch(page: 3);

        $this->assertSame(100, $this->pageRequest['body']['from']);
        $this->assertSame(50, $this->pageRequest['body']['size']);
    }

    public function testPageBelowOneIsTreatedAsFirstPage(): void
    {
        $this->fetch(page: 0);

        $this->assertSame(0, $this->pageRequest['body']['from']);
    }

    public function testMapsHitsToRowsAndTotal(): void
    {
        $result = $this->fetch(hits: [
            $this->pr(39812, thumbs: 8, linked: [39790], labels: ['Progress: ready for testing']),
            ['id' => 40001, 'title' => 'Not synced yet', 'url' => 'https://github.com/magento/magento2/pull/40001',
                'author' => null, 'created_at' => '2026-08-01T00:00:00Z'],
        ], total: 120);

        $this->assertSame(120, $result['total']);
        $this->assertSame([
            'number' => 39812,
            'title' => 'PR 39812',
            'url' => 'https://github.com/magento/magento2/pull/39812',
            'author' => 'jane',
            'created_at' => '2026-01-01T00:00:00Z',
            'thumbs_up_count' => 8,
            'linked_issues' => [39790],
            'labels' => [],
        ], $result['rows'][0]);
        $this->assertSame(0, $result['rows'][1]['thumbs_up_count']);
        $this->assertSame([], $result['rows'][1]['linked_issues']);
    }

    public function testRowLabelsAreTheUnionOfOwnAndLinkedIssueAreaComponentLabels(): void
    {
        $result = $this->fetch(
            issues: [
                101 => ['Area: Checkout', 'Component: Quote', 'Priority: P2'],
                102 => ['Area: Checkout', 'Component: Payment'],
            ],
            hits: [$this->pr(1, linked: [101, 102], labels: ['Component: Quote', 'Area: APIs', 'Progress: accept'])],
        );

        $this->assertSame(
            ['Area: APIs', 'Area: Checkout', 'Component: Payment', 'Component: Quote'],
            $result['rows'][0]['labels'],
        );
    }

    public function testLinkedIssueLabelsAreReadFromTheIssuesIndex(): void
    {
        $this->fetch(issues: [101 => ['Area: Checkout']], linkedIssues: [101, 205]);

        $this->assertCount(1, $this->issueRequests);
        $this->assertStringEndsWith('github-issues', $this->issueRequests[0]['index']);
        $this->assertSame(['values' => ['101', '205']], $this->issueRequests[0]['body']['query']['ids']);
    }

    public function testSkipsIssuesLookupWhenNoCandidateLinksAnIssue(): void
    {
        $this->fetch(linkedIssues: []);

        $this->assertSame([], $this->issueRequests);
    }

    public function testLabelFilterMatchesThePrsOwnLabelOrALinkedIssueCarryingIt(): void
    {
        $this->fetch(area: 'Area: Checkout', issues: [
            101 => ['Area: Checkout'],
            102 => ['Area: Catalog'],
            103 => ['Area: Checkout', 'Component: Quote'],
        ]);

        $this->assertContains(
            ['bool' => ['should' => [
                ['term' => ['labels.keyword' => 'Area: Checkout']],
                ['terms' => ['linked_issues' => [101, 103]]],
            ], 'minimum_should_match' => 1]],
            $this->pageRequest['body']['query']['bool']['filter'],
        );
    }

    public function testLabelOnNoLinkedIssueMatchesOnlyThePrsOwnLabel(): void
    {
        $this->fetch(area: 'Area: Checkout', issues: [102 => ['Area: Catalog']]);

        $this->assertContains(
            ['bool' => ['should' => [['term' => ['labels.keyword' => 'Area: Checkout']]], 'minimum_should_match' => 1]],
            $this->pageRequest['body']['query']['bool']['filter'],
        );
    }

    public function testAreaAndComponentMustBothMatch(): void
    {
        $this->fetch(area: 'Area: Checkout', component: 'Component: Quote', issues: [
            101 => ['Area: Checkout'],
            103 => ['Area: Checkout', 'Component: Quote'],
        ]);

        $filter = $this->pageRequest['body']['query']['bool']['filter'];
        $this->assertCount(3, $filter);
        $this->assertSame(['terms' => ['linked_issues' => [101, 103]]], $filter[1]['bool']['should'][1]);
        $this->assertSame(['terms' => ['linked_issues' => [103]]], $filter[2]['bool']['should'][1]);
    }

    public function testNoFilterAddsNoLabelClauses(): void
    {
        $this->fetch(issues: [101 => ['Area: Checkout']]);

        $this->assertSame([['term' => ['is_open' => true]]], $this->pageRequest['body']['query']['bool']['filter']);
    }

    public function testOptionsComeFromCandidatePrLabelsAndTheirLinkedIssues(): void
    {
        $result = $this->fetch(
            issues: [101 => ['Area: Checkout', 'Component: Quote', 'Priority: P2'], 102 => ['Area: Catalog']],
            prLabels: ['Component: Quote', 'Area: APIs'],
        );

        $this->assertSame(['Area: APIs', 'Area: Catalog', 'Area: Checkout'], $result['areaOptions']);
        $this->assertSame(['Component: Quote'], $result['componentOptions']);
    }

    public function testUnfilteredOptionsAreAggregatedOverAllCandidates(): void
    {
        $this->fetch();

        $this->assertCount(1, $this->facetRequests);
        $this->assertSame(0, $this->facetRequests[0]['body']['size']);
        $this->assertSame([['term' => ['is_open' => true]]], $this->facetRequests[0]['body']['query']['bool']['filter']);
        $this->assertSame('(Area|Component): .*', $this->facetRequests[0]['body']['aggs']['pr_labels']['terms']['include']);
    }

    public function testComponentOptionsAreNarrowedToCandidatesMatchingTheSelectedArea(): void
    {
        $result = $this->fetch(
            area: 'Area: Checkout',
            issues: [101 => ['Area: Checkout', 'Component: Quote'], 102 => ['Area: Catalog', 'Component: Admin']],
            prLabels: ['Component: Payment', 'Component: Admin'],
            narrowedFacets: ['Area: Checkout' => ['linked' => [101], 'prLabels' => ['Component: Payment']]],
        );

        $this->assertSame(['Component: Payment', 'Component: Quote'], $result['componentOptions']);
        // Area options are not narrowed by their own selection.
        $this->assertSame(['Area: Catalog', 'Area: Checkout'], $result['areaOptions']);
        $this->assertSame(
            ['bool' => ['should' => [
                ['term' => ['labels.keyword' => 'Area: Checkout']],
                ['terms' => ['linked_issues' => [101]]],
            ], 'minimum_should_match' => 1]],
            $this->facetRequests[1]['body']['query']['bool']['filter'][1],
        );
    }

    public function testAreaOptionsAreNarrowedToCandidatesMatchingTheSelectedComponent(): void
    {
        $result = $this->fetch(
            component: 'Component: Admin',
            issues: [101 => ['Area: Checkout', 'Component: Quote'], 102 => ['Area: Catalog', 'Component: Admin']],
            narrowedFacets: ['Component: Admin' => ['linked' => [102], 'prLabels' => ['Area: Admin UI']]],
        );

        $this->assertSame(['Area: Admin UI', 'Area: Catalog'], $result['areaOptions']);
        $this->assertSame(['Component: Admin', 'Component: Quote'], $result['componentOptions']);
    }

    public function testBothSelectionsNarrowEachOthersOptions(): void
    {
        $result = $this->fetch(
            area: 'Area: Checkout',
            component: 'Component: Quote',
            issues: [101 => ['Area: Checkout', 'Component: Quote'], 102 => ['Area: Catalog', 'Component: Quote']],
            narrowedFacets: [
                'Area: Checkout' => ['linked' => [101], 'prLabels' => []],
                'Component: Quote' => ['linked' => [101, 102], 'prLabels' => []],
            ],
        );

        $this->assertCount(3, $this->facetRequests);
        $this->assertSame(['Area: Catalog', 'Area: Checkout'], $result['areaOptions']);
        $this->assertSame(['Component: Quote'], $result['componentOptions']);
    }

    /**
     * @param  list<int>  $linked
     * @param  list<string>  $labels
     * @return array<string, mixed>
     */
    private function pr(int $number, int $thumbs = 0, array $linked = [], array $labels = []): array
    {
        return [
            'id' => $number,
            'title' => "PR {$number}",
            'url' => "https://github.com/magento/magento2/pull/{$number}",
            'author' => 'jane',
            'created_at' => '2026-01-01T00:00:00Z',
            'thumbs_up_count' => $thumbs,
            'linked_issues' => $linked,
            'labels' => $labels,
        ];
    }

    /**
     * Run the query against a mocked client that answers the candidate facet
     * aggregation, the linked-issue lookup, and the page search.
     *
     * @param  array<int, list<string>>  $issues  Issue number => labels, as stored in the issues index.
     * @param  list<int>|null  $linkedIssues  Facet buckets; defaults to the keys of $issues.
     * @param  list<string>  $prLabels  Facet buckets of the candidates' own Area/Component labels.
     * @param  list<array<string, mixed>>  $hits  `_source` of each page hit.
     * @param  array<string, array{linked: list<int>, prLabels: list<string>}>  $narrowedFacets  Facet buckets
     *                                                                                           for a facet request narrowed by the keyed label.
     * @return array{rows: list<array<string, mixed>>, total: int, areaOptions: list<string>, componentOptions: list<string>}
     */
    private function fetch(
        ?string $area = null,
        ?string $component = null,
        int $page = 1,
        array $issues = [],
        ?array $linkedIssues = null,
        array $prLabels = [],
        array $hits = [],
        ?int $total = null,
        array $narrowedFacets = [],
    ): array {
        $buckets = static fn (array $keys): array => ['buckets' => array_map(static fn ($k): array => ['key' => $k, 'doc_count' => 1], $keys)];

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(
            function (array $params) use ($issues, $linkedIssues, $prLabels, $hits, $total, $buckets, $narrowedFacets): array {
                if (str_ends_with($params['index'], 'github-issues')) {
                    $this->issueRequests[] = $params;
                    $ids = array_map('intval', $params['body']['query']['ids']['values']);

                    return ['hits' => ['hits' => array_values(array_map(
                        static fn (int $id): array => ['_id' => (string) $id, '_source' => ['labels' => $issues[$id]]],
                        array_filter($ids, static fn (int $id): bool => isset($issues[$id])),
                    ))]];
                }

                if (isset($params['body']['aggs'])) {
                    $this->facetRequests[] = $params;
                    $narrowedBy = $params['body']['query']['bool']['filter'][1]['bool']['should'][0]['term']['labels.keyword'] ?? null;
                    $facets = $narrowedFacets[$narrowedBy ?? '']
                        ?? ['linked' => $linkedIssues ?? array_keys($issues), 'prLabels' => $prLabels];

                    return ['aggregations' => [
                        'linked_issues' => $buckets($facets['linked']),
                        'pr_labels' => $buckets($facets['prLabels']),
                    ]];
                }

                $this->pageRequest = $params;

                return ['hits' => [
                    'total' => ['value' => $total ?? count($hits)],
                    'hits' => array_map(static fn (array $s): array => ['_source' => $s], $hits),
                ]];
            }
        );

        return (new CommunityPickCandidatesQuery($client))->execute($area, $component, $page);
    }
}
