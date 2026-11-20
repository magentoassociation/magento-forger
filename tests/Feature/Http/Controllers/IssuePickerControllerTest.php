<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use Mockery;
use OpenSearch\Client;
use RuntimeException;
use Tests\TestCase;

class IssuePickerControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testRendersOpenIssuesWithCountsAndRowLinks(): void
    {
        $this->bindClient(
            [$this->issue(41804, ['Area: Catalog', 'Component: Product Grid'], comments: 3)],
            total: 14,
            openTotal: 412,
        );

        $response = $this->get(route('issues.pick'));

        $response->assertOk();
        $response->assertSee('<h1>Pick an Issue</h1>', false);
        $response->assertSee('Narrow the open issues by area and component. Pick one, then open it on GitHub to claim it.');
        $response->assertSee('Showing 14 of 412 open issues');
        $response->assertSee('<a class="ipr-link" href="https://github.com/magento/magento2/issues/41804" target="_blank" rel="noopener">', false);
        $response->assertSee('<span class="ipr-num">#41804</span>', false);
        $response->assertSee('<td class="ipr-label">Catalog</td>', false);
        $response->assertSee('<td class="ipr-label ip-col-component">Product Grid</td>', false);
        $response->assertSee('>17 Aug 2026</time>', false);
        $response->assertSee('<td class="ipr-mono ip-col-comments">3</td>', false);
    }

    public function testIsLinkedFromTheIssuesMenu(): void
    {
        $this->bindClient([]);

        $this->get(route('issues.pick'))
            ->assertSee('href="'.route('issues.pick').'">Pick an Issue</a>', false)
            ->assertSee('href="'.route('issues.byMonth').'">By Month</a>', false);
    }

    public function testHidesAssignedAndPrIssuesByDefault(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests, openPrIssues: [101, 102]);

        $this->get(route('issues.pick'))
            ->assertOk()
            ->assertSee('data-param="showAssigned" aria-pressed="false" title="Include issues that are already assigned"', false)
            ->assertSee('data-param="showPr" aria-pressed="false" title="Include issues that already have a PR"', false)
            ->assertSeeInOrder(['Include assigned', 'Include with PRs'])
            ->assertSee('<input type="hidden" name="showAssigned" value="1" disabled>', false);

        $mustNot = $requests[0]['body']['query']['bool']['must_not'];
        $this->assertContains(['exists' => ['field' => 'assignees']], $mustNot);
        $this->assertContains(['term' => ['closed_by_merged_pr' => true]], $mustNot);
        $this->assertContains(['ids' => ['values' => ['101', '102']]], $mustNot);
        // Stale once a closing PR opens, since that doesn't bump the issue's updatedAt.
        $this->assertNotContains(['term' => ['has_closing_pr' => true]], $mustNot);
    }

    public function testShowChipsAddTakenIssuesBack(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests, openPrIssues: [101]);

        $this->get(route('issues.pick', ['showAssigned' => 1, 'showPr' => 1]))
            ->assertOk()
            ->assertSee('data-param="showAssigned" aria-pressed="true"', false)
            ->assertSee('data-param="showPr" aria-pressed="true"', false);

        $this->assertArrayNotHasKey('must_not', $requests[0]['body']['query']['bool']);
    }

    public function testShowAssignedAloneStillHidesPrIssues(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests);

        $this->get(route('issues.pick', ['showAssigned' => 1]))->assertOk();

        $mustNot = $requests[0]['body']['query']['bool']['must_not'];
        $this->assertNotContains(['exists' => ['field' => 'assignees']], $mustNot);
        $this->assertContains(['term' => ['closed_by_merged_pr' => true]], $mustNot);
    }

    public function testListsAreaOptionsByNameWithoutCountsAndFiltersByArea(): void
    {
        $requests = [];
        $facetRequests = [];
        $this->bindClient([], requests: $requests, areas: ['Area: Catalog' => 42, 'Area: Checkout' => 7], facetRequests: $facetRequests);

        $response = $this->get(route('issues.pick', ['area' => 'Area: Catalog']));

        $response->assertSee('<option value="Area: Catalog" selected>Catalog</option>', false);
        $response->assertSee('<option value="Area: Checkout" >Checkout</option>', false);
        $response->assertDontSee('Catalog · 42');
        $shown = $facetRequests[0]['body']['aggs']['shown']['aggs'];
        $this->assertSame(['_key' => 'asc'], $shown['area']['aggs']['labels']['terms']['order']);
        $this->assertSame(['_key' => 'asc'], $shown['component']['aggs']['labels']['terms']['order']);
        $this->assertContains(
            ['term' => ['labels.keyword' => ['value' => 'Area: Catalog', 'case_insensitive' => true]]],
            $requests[0]['body']['query']['bool']['filter'],
        );
    }

    public function testSortsOptionsIgnoringCase(): void
    {
        // OpenSearch returns keys byte-wise: uppercase before lowercase.
        $this->bindClient([], areas: ['Area: Zeta' => 1, 'Area: alpha' => 1], components: ['Component: Yak' => 1, 'Component: bee' => 1]);

        $this->get(route('issues.pick'))
            ->assertSeeInOrder(['>alpha</option>', '>Zeta</option>'], false)
            ->assertSeeInOrder(['>bee</option>', '>Yak</option>'], false);
    }

    public function testComponentOptionsAreNarrowedToTheSelectedArea(): void
    {
        $facetRequests = [];
        $this->bindClient([], areas: ['Area: Catalog' => 42], components: ['Component: Product Grid' => 9], facetRequests: $facetRequests);

        $this->get(route('issues.pick', ['area' => 'Area: Catalog']))
            ->assertSee('<option value="Component: Product Grid" >Product Grid</option>', false);

        $this->assertSame(
            ['bool' => ['filter' => [['term' => ['labels.keyword' => ['value' => 'Area: Catalog', 'case_insensitive' => true]]]]]],
            $facetRequests[0]['body']['aggs']['shown']['aggs']['component']['filter'],
        );
    }

    public function testOptionsLeaveOutIssuesTheChipsHide(): void
    {
        $requests = [];
        $facetRequests = [];
        $this->bindClient([], requests: $requests, openPrIssues: [101], facetRequests: $facetRequests);

        $this->get(route('issues.pick'))->assertSee('of 412 open issues');

        // Options only count issues the list can show; the total still counts every open issue.
        $this->assertSame(['term' => ['is_open' => true]], $facetRequests[0]['body']['query']);
        $this->assertSame(
            ['bool' => ['must_not' => $requests[0]['body']['query']['bool']['must_not']]],
            $facetRequests[0]['body']['aggs']['shown']['filter'],
        );
    }

    public function testOptionsCoverEveryOpenIssueWithBothChipsOn(): void
    {
        $facetRequests = [];
        $this->bindClient([], facetRequests: $facetRequests);

        $this->get(route('issues.pick', ['showAssigned' => 1, 'showPr' => 1]));

        $this->assertEquals(['match_all' => (object) []], $facetRequests[0]['body']['aggs']['shown']['filter']);
    }

    public function testClearsAComponentThatIsNotInTheAreasList(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests, areas: ['Area: Checkout' => 7], components: ['Component: Cart' => 3]);

        $response = $this->get(route('issues.pick', ['area' => 'Area: Checkout', 'component' => 'Component: Product Grid']));

        $response->assertDontSee('Product Grid');
        $this->assertCount(2, $requests[0]['body']['query']['bool']['filter']);
    }

    public function testKeepsAComponentStillInTheAreasList(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests, areas: ['Area: Catalog' => 42], components: ['Component: Product Grid' => 9]);

        $this->get(route('issues.pick', ['area' => 'Area: Catalog', 'component' => 'Component: PRODUCT GRID']))
            ->assertSee('<option value="Component: Product Grid" selected>', false);

        $this->assertCount(3, $requests[0]['body']['query']['bool']['filter']);
    }

    public function testDisablesComponentWhenTheAreaHasNone(): void
    {
        $this->bindClient([], areas: ['Area: Catalog' => 42]);

        $this->get(route('issues.pick', ['area' => 'Area: Catalog']))
            ->assertSee('name="component" class="ip-select is-empty" disabled', false)
            ->assertSee('No components for this area');
    }

    public function testSortsByComments(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests);

        $this->get(route('issues.pick', ['sort' => 'comments']))
            ->assertSee('<option value="comments" selected>Most comments</option>', false);

        $this->assertSame(
            [['comments_count' => ['order' => 'desc']], ['created_at' => ['order' => 'asc']]],
            $requests[0]['body']['sort'],
        );
    }

    public function testDefaultsToOldestFirst(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests);

        $this->get(route('issues.pick'));

        $this->assertSame([['created_at' => ['order' => 'asc']]], $requests[0]['body']['sort']);
    }

    public function testShowsTwentyFiveMoreUpToTheTotal(): void
    {
        $requests = [];
        $this->bindClient([$this->issue(1)], total: 60, requests: $requests);

        $response = $this->get(route('issues.pick'));

        $this->assertSame(25, $requests[0]['body']['size']);
        $response->assertSee('pick?rows=50">Show 25 more</a>', false);
    }

    public function testDropsShowMoreAtTheEnd(): void
    {
        $this->bindClient([$this->issue(1)], total: 30);

        $this->get(route('issues.pick', ['rows' => 50]))->assertDontSee('Show 25 more');
    }

    public function testShowsTheEmptyState(): void
    {
        $this->bindClient([], total: 0, openTotal: 412);

        $this->get(route('issues.pick'))
            ->assertSee('Showing 0 of 412 open issues')
            ->assertSee('No open issues match these filters.')
            ->assertSee('Try turning on an Include filter, or choose a different area.')
            ->assertDontSee('<table', false);
    }

    public function testRejectsInvalidFilters(): void
    {
        $this->bindClient([]);

        $this->get(route('issues.pick', ['area' => 'Catalog']))->assertRedirect();
        $this->get(route('issues.pick', ['sort' => 'votes']))->assertRedirect();
        $this->get(route('issues.pick', ['showPr' => 'yes']))->assertRedirect();
    }

    public function testShowsDataMissingWhenTheIndexIsAbsent(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andThrow(new RuntimeException('index_not_found_exception'));
        $this->app->instance(Client::class, $client);

        $this->get(route('issues.pick'))
            ->assertOk()
            ->assertSee('The issues index is empty or missing.')
            ->assertDontSee('class="ip-filters"', false);
    }

    public function testRowsShowProgressAndIssueLabelsIgnoringCase(): void
    {
        $this->bindClient([$this->issue(41804, ['Area: Catalog', 'Progress: ready for dev', 'issue: Confirmed', 'Priority: P2'])]);

        $response = $this->get(route('issues.pick'));

        $response->assertSeeInOrder(['<span class="ipr-pill">Issue: Confirmed</span>', '<span class="ipr-pill ipr-pill--progress">Progress: Ready for Dev</span>'], false);
        $response->assertDontSee('<span class="ipr-pill">Priority: P2</span>', false);
        $response->assertDontSee('<span class="ipr-pill">Area: Catalog</span>', false);
    }

    public function testFiltersByProgressAndIssueLabels(): void
    {
        $requests = [];
        $this->bindClient(
            [],
            requests: $requests,
            progress: ['Progress: ready for dev' => 1],
            issueLabels: ['Issue: Confirmed' => 1],
        );

        $response = $this->get(route('issues.pick', ['progress' => 'Progress: ready for dev', 'issue' => 'Issue: Confirmed']));

        $response->assertSee('<option value="Progress: ready for dev" selected>Ready for Dev</option>', false);
        $response->assertSee('<option value="Issue: Confirmed" selected>Confirmed</option>', false);
        $filter = $requests[0]['body']['query']['bool']['filter'];
        $this->assertContains(['term' => ['labels.keyword' => ['value' => 'Progress: ready for dev', 'case_insensitive' => true]]], $filter);
        $this->assertContains(['term' => ['labels.keyword' => ['value' => 'Issue: Confirmed', 'case_insensitive' => true]]], $filter);
    }

    public function testEveryLabelListIsNarrowedByTheOtherSelections(): void
    {
        $facetRequests = [];
        $this->bindClient(
            [],
            areas: ['Area: Catalog' => 1],
            components: ['Component: Product Grid' => 1],
            facetRequests: $facetRequests,
            progress: ['Progress: ready for dev' => 1],
            issueLabels: ['Issue: Confirmed' => 1],
        );

        $this->get(route('issues.pick', [
            'area' => 'Area: Catalog',
            'component' => 'Component: Product Grid',
            'progress' => 'Progress: ready for dev',
            'issue' => 'Issue: Confirmed',
        ]));

        $term = static fn (string $label): array => ['term' => ['labels.keyword' => ['value' => $label, 'case_insensitive' => true]]];
        $narrowedBy = static fn (string ...$labels): array => ['bool' => ['filter' => array_map($term, $labels)]];
        $aggs = $facetRequests[0]['body']['aggs']['shown']['aggs'];
        // Each list is narrowed by every other selection, but not by its own.
        $this->assertSame($narrowedBy('Component: Product Grid', 'Progress: ready for dev', 'Issue: Confirmed'), $aggs['area']['filter']);
        $this->assertSame($narrowedBy('Area: Catalog', 'Progress: ready for dev', 'Issue: Confirmed'), $aggs['component']['filter']);
        $this->assertSame($narrowedBy('Area: Catalog', 'Component: Product Grid', 'Issue: Confirmed'), $aggs['progress']['filter']);
        $this->assertSame($narrowedBy('Area: Catalog', 'Component: Product Grid', 'Progress: ready for dev'), $aggs['issueStatus']['filter']);
        // OpenSearch's include regex has no case flag, so the prefix is spelled per letter.
        $this->assertSame('[Pp][Rr][Oo][Gg][Rr][Ee][Ss][Ss].*', $aggs['progress']['aggs']['labels']['terms']['include']);
        $this->assertSame('[Ii][Ss][Ss][Uu][Ee].*', $aggs['issueStatus']['aggs']['labels']['terms']['include']);
    }

    public function testConflictingSelectionsClearTheLowestPriorityFirst(): void
    {
        // No issue has both Area: Catalog and Progress: ready for dev, so each hides the other
        // from its list. Clearing both would lose the Area too; only Progress should go.
        $hasFilter = static fn (array $agg, string $label): bool => str_contains(json_encode($agg['filter']), $label);
        $resultQueries = [];
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(static function (array $params) use ($hasFilter, &$resultQueries): array {
            if (str_ends_with($params['index'], 'github-pull-requests')) {
                return ['aggregations' => ['linked_issues' => ['buckets' => []]]];
            }
            if (! isset($params['body']['aggs'])) {
                $resultQueries[] = $params['body']['query'];

                return ['hits' => ['total' => ['value' => 0], 'hits' => []]];
            }
            $aggs = $params['body']['aggs']['shown']['aggs'];
            $list = static fn (array $labels): array => ['labels' => ['buckets' => array_map(static fn (string $l): array => ['key' => $l], $labels)]];

            return [
                'hits' => ['total' => ['value' => 10]],
                'aggregations' => ['shown' => [
                    'area' => $list($hasFilter($aggs['area'], 'ready for dev') ? ['Area: Checkout'] : ['Area: Catalog', 'Area: Checkout']),
                    'component' => $list([]),
                    'progress' => $list($hasFilter($aggs['progress'], 'Area: Catalog') ? ['Progress: done'] : ['Progress: done', 'Progress: ready for dev']),
                    'issueStatus' => $list([]),
                ]],
            ];
        });
        $this->app->instance(Client::class, $client);

        $response = $this->get(route('issues.pick', ['area' => 'Area: Catalog', 'progress' => 'Progress: ready for dev']));

        $response->assertSee('<option value="Area: Catalog" selected>Catalog</option>', false);
        $response->assertDontSee('value="Progress: ready for dev" selected', false);
        $this->assertStringContainsString('Area: Catalog', json_encode($resultQueries[0]));
        $this->assertStringNotContainsString('ready for dev', json_encode($resultQueries[0]));
    }

    public function testProgressOptionsFollowTheWorkflowOrder(): void
    {
        // As OpenSearch returns them: byte-wise, uppercase first.
        $this->bindClient([], progress: [
            'Progress: PR Created' => 1,
            'Progress: PR in progress' => 1,
            'Progress: dev in progress' => 1,
            'Progress: done' => 1,
            'Progress: on hold' => 1,
            'Progress: ready for dev' => 1,
            'Progress: ready for grooming' => 1,
        ]);

        $this->get(route('issues.pick'))->assertSeeInOrder([
            '>Ready for Grooming</option>',
            '>Ready for Dev</option>',
            '>Dev in Progress</option>',
            '>PR Created</option>',
            '>PR in Progress</option>',
            '>Done</option>',
            // Not in the workflow list: after it, by name.
            '>On Hold</option>',
        ], false);
    }

    public function testProgressAndIssueOptionsAreApTitleCase(): void
    {
        $this->bindClient(
            [],
            progress: ['Progress: PR Created' => 1, 'Progress: ready for grooming' => 1],
            issueLabels: ['Issue: Format is not valid' => 1, 'Issue: On Hold' => 1],
        );

        $this->get(route('issues.pick'))
            ->assertSee('<option value="Progress: PR Created" >PR Created</option>', false)
            ->assertSee('<option value="Progress: ready for grooming" >Ready for Grooming</option>', false)
            ->assertSee('<option value="Issue: Format is not valid" >Format Is Not Valid</option>', false)
            ->assertSee('<option value="Issue: On Hold" >On Hold</option>', false);
    }

    public function testClearsAProgressLabelThatIsNotInItsList(): void
    {
        $requests = [];
        $this->bindClient([], requests: $requests, progress: ['Progress: ready for dev' => 1]);

        $response = $this->get(route('issues.pick', ['progress' => 'Progress: done']));

        $response->assertDontSee('value="Progress: done"', false);
        $this->assertCount(1, $requests[0]['body']['query']['bool']['filter']);
    }

    public function testDisablesProgressAndIssueWhenTheyHaveNoOptions(): void
    {
        $this->bindClient([]);

        $this->get(route('issues.pick'))
            ->assertSee('No progress labels')
            ->assertSee('No issue labels')
            ->assertSee('name="progress" class="ip-select is-empty" disabled', false);
    }

    public function testRejectsProgressAndIssueValuesWithTheWrongPrefix(): void
    {
        $this->bindClient([]);

        $this->get(route('issues.pick', ['progress' => 'Area: Catalog']))->assertRedirect();
        $this->get(route('issues.pick', ['issue' => 'Priority: P1']))->assertRedirect();
        $this->get(route('issues.pick', ['progress' => 'progress: ready for dev']))->assertSessionHasNoErrors();
    }

    /**
     * @param  list<string>  $labels
     * @return array<string, mixed>
     */
    private function issue(int $number, array $labels = [], int $comments = 0): array
    {
        return [
            'id' => $number,
            'title' => "Issue {$number}",
            'url' => "https://github.com/magento/magento2/issues/{$number}",
            'labels' => $labels,
            'created_at' => '2026-08-17T10:00:00Z',
            'comments_count' => $comments,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sources  Result rows.
     * @param  list<array<string, mixed>>  $requests  Receives each result search.
     * @param  array<string, int>  $areas  Area label => bucket doc_count (the page ignores the count).
     * @param  array<string, int>  $components  Component label => bucket doc_count (ignored likewise).
     * @param  list<int>  $openPrIssues  Issues an open PR closes.
     * @param  list<array<string, mixed>>  $facetRequests  Receives each facet search.
     * @param  array<string, int>  $progress  Progress label => bucket doc_count (ignored).
     * @param  array<string, int>  $issueLabels  Issue label => bucket doc_count (ignored).
     */
    private function bindClient(
        array $sources,
        ?int $total = null,
        int $openTotal = 412,
        array &$requests = [],
        array $areas = [],
        array $components = [],
        array $openPrIssues = [],
        array &$facetRequests = [],
        array $progress = [],
        array $issueLabels = [],
    ): void {
        $buckets = static fn (array $counts): array => ['buckets' => array_map(
            static fn ($key, int $count): array => ['key' => $key, 'doc_count' => $count],
            array_keys($counts),
            $counts,
        )];

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(static function (array $params) use (
            $sources, $total, $openTotal, &$requests, $areas, $components, $openPrIssues, &$facetRequests, $buckets, $progress, $issueLabels
        ): array {
            if (str_ends_with($params['index'], 'github-pull-requests')) {
                return ['aggregations' => ['linked_issues' => $buckets(array_fill_keys($openPrIssues, 1))]];
            }

            if (isset($params['body']['aggs'])) {
                $facetRequests[] = $params;

                return [
                    'hits' => ['total' => ['value' => $openTotal]],
                    'aggregations' => ['shown' => [
                        'area' => ['labels' => $buckets($areas)],
                        'component' => ['labels' => $buckets($components)],
                        'progress' => ['labels' => $buckets($progress)],
                        'issueStatus' => ['labels' => $buckets($issueLabels)],
                    ]],
                ];
            }

            $requests[] = $params;

            return ['hits' => [
                'total' => ['value' => $total ?? count($sources)],
                'hits' => array_map(static fn (array $s): array => ['_source' => $s], $sources),
            ]];
        });
        $this->app->instance(Client::class, $client);
    }
}
