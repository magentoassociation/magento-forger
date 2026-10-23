<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\CommunityPickedResult;
use App\Models\RoleEligibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use OpenSearch\Client;
use RuntimeException;
use Tests\TestCase;

class CommunityPickedControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testRendersCandidatesWithVoteLinks(): void
    {
        $this->bindClient([[
            'id' => 39812,
            'title' => 'Exclude orphan plugins from code generation',
            'url' => 'https://github.com/magento/magento2/pull/39812',
            'author' => 'swnsma',
            'created_at' => now()->subMonths(3)->toIso8601String(),
            'thumbs_up_count' => 8,
            'linked_issues' => [39790],
        ]]);

        $response = $this->get('/prs/community-picked');

        $response->assertOk();
        $response->assertSee('<h1>Community-Picked PRs</h1>', false);
        $response->assertDontSee('How community picking works');
        $response->assertSee('then click 👍 on the PR description. After each voting period');
        $response->assertSee('Exclude orphan plugins from code generation');
        $response->assertSee('href="https://github.com/magento/magento2/pull/39812" target="_blank" rel="noopener"', false);
        $response->assertSee('<span class="cpr-votes">8<span class="visually-hidden"> votes</span></span>', false);
        $response->assertDontSee('aria-label="Vote for', false);
        $response->assertSee('href="https://github.com/'.config('github.repo').'/issues/39790"', false);
        $response->assertSee('href="'.route('leaderboard.detail', ['board' => 'contributor', 'login' => 'swnsma']).'"
                                       title="Contributions — @swnsma">@swnsma</a>', false);
        $response->assertSee('<span class="cpr-issues-label">Issue</span>', false);
        $response->assertDontSee('href="https://github.com/swnsma"', false);
        $response->assertSee('3 months ago');
    }

    public function testIsHiddenFromGuests(): void
    {
        $this->bindClient([]);
        auth()->logout();

        $this->get(route('prs.communityPicked'))->assertNotFound();
        $this->get(route('leaderboard.scoring'))
            ->assertOk()
            ->assertDontSee('href="'.route('prs.communityPicked').'"', false);
    }

    public function testIsHiddenFromSignedInUsersOutsideTheCouncil(): void
    {
        $this->bindClient([]);
        $user = User::factory()->create();
        RoleEligibility::query()->create(['login' => $user->github_username, 'role' => 'maintainer', 'active' => true]);

        $this->actingAs($user)->get(route('prs.communityPicked'))->assertNotFound();
        $this->actingAs($user)->get(route('leaderboard.scoring'))
            ->assertDontSee('href="'.route('prs.communityPicked').'"', false);
    }

    public function testIsHiddenFromFormerCouncilMembers(): void
    {
        $this->bindClient([]);
        $user = User::factory()->create();
        RoleEligibility::query()->create(['login' => $user->github_username, 'role' => 'community-council', 'active' => false]);

        $this->actingAs($user)->get(route('prs.communityPicked'))->assertNotFound();
    }

    public function testIsShownToActiveCouncilMembers(): void
    {
        $this->bindClient([]);
        $user = User::factory()->create();
        RoleEligibility::query()->create(['login' => $user->github_username, 'role' => 'community-council', 'active' => true]);

        $this->actingAs($user)->get(route('prs.communityPicked'))
            ->assertOk()
            ->assertSee('href="'.route('prs.communityPicked').'">Community-Picked</a>', false);
    }

    public function testIsShownToAdmins(): void
    {
        $this->bindClient([]);

        $this->get(route('prs.communityPicked'))->assertOk();
    }

    public function testShowsFiftyMoreUpToTheTotal(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], total: 120, requests: $requests);

        $response = $this->get('/prs/community-picked');

        $response->assertOk();
        $this->assertSame(50, $requests[0]['body']['size']);
        $response->assertSee('120 open PRs');
        $response->assertSee('community-picked?rows=100">Show 50 more</a>', false);
        $response->assertSee('Showing 1–1 of 120');
        $response->assertDontSee('page=2', false);
    }

    public function testRowsDeepensTheListAndDropsTheButtonAtTheEnd(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], total: 120, requests: $requests);

        $response = $this->get('/prs/community-picked?rows=150');

        $this->assertSame(150, $requests[0]['body']['size']);
        $this->assertArrayNotHasKey('from', $requests[0]['body']);
        $response->assertDontSee('Show 50 more');
    }

    public function testCountsASinglePr(): void
    {
        $this->bindClient([$this->candidate(1)]);

        $this->get('/prs/community-picked')
            ->assertSee('1 open PR<', false)
            ->assertSee('Showing all 1')
            ->assertDontSee('Show 50 more');
    }

    public function testRowWithNoLabelsOrIssuesHasNoSecondLine(): void
    {
        $this->bindClient([$this->candidate(1)]);

        $this->get('/prs/community-picked')
            ->assertDontSee('class="cpr-line2"', false)
            ->assertDontSee('cpr-issues-label">', false);
    }

    public function testShowsEffectiveLabelsAndFilterOptions(): void
    {
        $this->bindClient(
            [$this->candidate(1, linked: [101], labels: ['Area: APIs'])],
            prLabels: ['Area: APIs'],
            issues: [101 => ['Area: Checkout', 'Component: Quote']],
        );

        $response = $this->get('/prs/community-picked');

        $response->assertSeeInOrder(['Area: APIs', 'Area: Checkout', 'Component: Quote']);
        $response->assertSee('<span class="cpr-area">Checkout</span>', false);
        $response->assertSee('<span class="cpr-comp">Quote</span>', false);
        $response->assertSee('<option value="">All areas</option>', false);
        $response->assertSee('<option value="">All components</option>', false);
        $response->assertSee('<option value="Area: Checkout" >Checkout</option>', false);
        $response->assertSee('<option value="Component: Quote" >Quote</option>', false);
    }

    public function testFiltersPassThroughAndStaySelected(): void
    {
        $requests = [];
        $this->bindClient(
            [$this->candidate(1)],
            total: 120,
            requests: $requests,
            issues: [101 => ['Area: Checkout', 'Component: Quote']],
        );

        $response = $this->get('/prs/community-picked?area='.urlencode('Area: Checkout').'&component='.urlencode('Component: Quote'));

        $response->assertOk();
        $this->assertCount(3, $requests[0]['body']['query']['bool']['filter']);
        $response->assertSee('<option value="Area: Checkout" selected>Checkout</option>', false);
        $response->assertSee('<option value="Component: Quote" selected>Quote</option>', false);
        $response->assertSee('area=Area%3A%20Checkout&amp;component=Component%3A%20Quote&amp;rows=100', false);
    }

    public function testKeepsASharedFilterSelectableWhenNoCandidateCarriesIt(): void
    {
        $this->bindClient([]);

        $response = $this->get('/prs/community-picked?area='.urlencode('Area: Gone'));

        $response->assertSee('<option value="Area: Gone" selected>Gone</option>', false);
        $response->assertSee('No candidates match these filters.');
    }

    public function testASharedLinkInAnOldSpellingSelectsTheCurrentLabel(): void
    {
        $this->bindClient([], prLabels: ['Area: Test Framework']);

        $response = $this->get('/prs/community-picked?area='.urlencode('Area: Test framework'));

        $response->assertSee('<option value="Area: Test Framework" selected>Test Framework</option>', false);
        $response->assertDontSee('<option value="Area: Test framework"', false);
    }

    public function testClearFiltersIsHiddenWithNothingToClear(): void
    {
        $this->bindClient([$this->candidate(1)]);

        $this->get('/prs/community-picked?sort=oldest')
            ->assertSee('<button type="button" class="cp-clear"  hidden >Clear filters</button>', false)
            ->assertDontSee('is-set"', false);
    }

    public function testSetFiltersShowClearFiltersAndMarkTheirControls(): void
    {
        $this->bindClient([$this->candidate(1)], prLabels: ['Area: Checkout']);

        $this->get('/prs/community-picked?area='.urlencode('Area: Checkout').'&author=jane')
            ->assertSee('<button type="button" class="cp-clear" >Clear filters</button>', false)
            ->assertSee('<select name="area" class="cp-select is-set"', false)
            ->assertSee('<select name="component" class="cp-select"', false)
            ->assertSee('class="cp-input is-set"', false)
            ->assertSee('class="lb-search-input"', false);
    }

    public function testRejectsLabelsOutsideTheirPrefix(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?area='.urlencode('Component: Quote'))->assertSessionHasErrors('area');
        $this->get('/prs/community-picked?component='.urlencode('Priority: P1'))->assertSessionHasErrors('component');
        $this->get('/prs/community-picked?area[]=x')->assertSessionHasErrors('area');
    }

    public function testAuthorFilterPassesThroughAndOffersCandidateAuthors(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], total: 120, requests: $requests, authors: ['jane', 'swnsma']);

        $response = $this->get('/prs/community-picked?author=SWNSMA');

        $response->assertOk();
        $this->assertContains(
            ['term' => ['author.keyword' => ['value' => 'SWNSMA', 'case_insensitive' => true]]],
            $requests[0]['body']['query']['bool']['filter'],
        );
        $response->assertSee('name="author" value="SWNSMA"', false);
        $response->assertSee('<option value="swnsma"></option>', false);
        $response->assertSee('author=SWNSMA&amp;rows=100', false);
    }

    public function testAcceptsBotLogins(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?author='.urlencode('dependabot[bot]'))->assertSessionHasNoErrors()->assertOk();
    }

    public function testRejectsAuthorsThatAreNotGitHubLogins(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?author='.urlencode('jane doe'))->assertSessionHasErrors('author');
        $this->get('/prs/community-picked?author='.urlencode('a"b'))->assertSessionHasErrors('author');
        $this->get('/prs/community-picked?author='.str_repeat('a', 51))->assertSessionHasErrors('author');
        $this->get('/prs/community-picked?author[]=x')->assertSessionHasErrors('author');
    }

    public function testTitleFilterPassesThroughAndStaysFilledIn(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], total: 120, requests: $requests);

        $response = $this->get('/prs/community-picked?title='.urlencode('orphan plug'));

        $response->assertOk();
        $this->assertContains(
            ['match_bool_prefix' => ['title' => ['query' => 'orphan plug', 'operator' => 'and']]],
            $requests[0]['body']['query']['bool']['filter'],
        );
        $response->assertSee('name="title" value="orphan plug"', false);
        $response->assertSee('title=orphan%20plug&amp;rows=100', false);
    }

    public function testTitleBoxFindsAPrByNumber(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(41427)], requests: $requests);

        $response = $this->get('/prs/community-picked?title='.urlencode('#41427'));

        $response->assertOk();
        $this->assertContains(['term' => ['id' => 41427]], $requests[0]['body']['query']['bool']['filter'][1]['bool']['should']);
        $response->assertSee('name="title" value="#41427"', false);
    }

    public function testRejectsOverlongOrNonStringTitle(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?title='.str_repeat('a', 201))->assertSessionHasErrors('title');
        $this->get('/prs/community-picked?title[]=x')->assertSessionHasErrors('title');
    }

    public function testDefaultsToMostVotesSort(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], requests: $requests);

        $response = $this->get('/prs/community-picked');

        $this->assertArrayHasKey('thumbs_up_count', $requests[0]['body']['sort'][0]);
        $response->assertSee('<option value="votes" selected>Most votes</option>', false);
    }

    public function testSortsByAgeAndKeepsSortWhenShowingMore(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], total: 120, requests: $requests);

        $response = $this->get('/prs/community-picked?sort=oldest&area='.urlencode('Area: Checkout'));

        $response->assertOk();
        $this->assertSame(['created_at' => ['order' => 'asc']], $requests[0]['body']['sort'][0]);
        $response->assertSee('<option value="oldest" selected>Oldest first</option>', false);
        $response->assertSee('sort=oldest&amp;area=Area%3A%20Checkout&amp;rows=100', false);
    }

    public function testCanSwitchBackToMostVotes(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], requests: $requests);

        $this->get('/prs/community-picked?sort=votes')->assertOk();

        $this->assertArrayHasKey('thumbs_up_count', $requests[0]['body']['sort'][0]);
    }

    public function testRejectsUnknownSort(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?sort=random')->assertSessionHasErrors('sort');
    }

    public function testRejectsInvalidRows(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?rows=abc')->assertSessionHasErrors('rows');
        $this->get('/prs/community-picked?rows=0')->assertSessionHasErrors('rows');
        $this->get('/prs/community-picked?rows=10001')->assertSessionHasErrors('rows');
    }

    public function testShowsEmptyStateWhenNoCandidates(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked')->assertSee('No community pick candidates right now.');
    }

    public function testShowsPlaceholderWhenIndexMissing(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andThrow(new RuntimeException('index_not_found_exception'));
        $this->app->instance(Client::class, $client);

        $response = $this->get('/prs/community-picked');

        $response->assertOk();
        $response->assertSee('The pull-requests index is empty or missing.');
        $response->assertDontSee('No community pick candidates right now.');
    }

    public function testReportsOtherSearchFailuresWithoutLeakingDetails(): void
    {
        $exceptions = Exceptions::fake();
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andThrow(new RuntimeException('secret-cluster-host:9200 refused'));
        $this->app->instance(Client::class, $client);

        $response = $this->get('/prs/community-picked');

        $response->assertStatus(500);
        $response->assertDontSee('secret-cluster-host');
        $exceptions->assertReported(RuntimeException::class);
    }

    public function testIsLinkedFromPrsMenuOnly(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked')
            ->assertSee('href="'.route('prs.communityPicked').'">Community-Picked</a>', false)
            ->assertDontSee('href="'.route('prs.communityPicked').'">Community-Picked PRs</a>', false);
    }

    public function testShowsLatestMonthlyResultsAndListsPastMonthsNewestFirst(): void
    {
        $this->bindClient([]);
        CommunityPickedResult::factory()->create(['month' => '2026-08-01', 'posted_on' => '2026-09-01', 'image_path' => 'community-picked/aug.png']);
        CommunityPickedResult::factory()->create(['month' => '2026-09-01', 'posted_on' => '2026-10-01', 'image_path' => 'community-picked/sep.png']);

        $response = $this->get('/prs/community-picked');

        $response->assertOk();
        $response->assertSeeInOrder(['September 2026 results', 'Open for voting'], false);
        $response->assertSee('<time class="cp-posted" datetime="2026-10-01" title="October 1, 2026">Posted Oct 1, 2026</time>', false);
        $response->assertSee('alt="September 2026 Community-Picked results: open PRs on GitHub sorted by reactions"', false);
        $response->assertSee('src="'.Storage::disk('public')->url('community-picked/sep.png').'"', false);
        $response->assertSee('View full results');
        $response->assertSeeInOrder(['<option value="">Past results</option>', '<option value="2026-09">September 2026</option>', '<option value="2026-08">August 2026</option>'], false);
        $response->assertSee('data-src="'.Storage::disk('public')->url('community-picked/aug.png').'"', false);
    }

    public function testHidesDisabledMonths(): void
    {
        $this->bindClient([]);
        CommunityPickedResult::factory()->create(['month' => '2026-08-01', 'posted_on' => '2026-09-01']);
        CommunityPickedResult::factory()->create(['month' => '2026-09-01', 'posted_on' => '2026-10-01', 'enabled' => false]);

        $response = $this->get('/prs/community-picked');

        $response->assertSee('August 2026 results');
        $response->assertDontSee('September 2026');
    }

    public function testOmitsTheResultsBandWhenEveryMonthIsDisabled(): void
    {
        $this->bindClient([]);
        CommunityPickedResult::factory()->create(['enabled' => false]);

        $this->get('/prs/community-picked')->assertDontSee('class="cp-band"', false);
    }

    public function testOmitsTheResultsBandUntilResultsArePosted(): void
    {
        $this->bindClient([]);

        $response = $this->get('/prs/community-picked');

        $response->assertDontSee('class="cp-band"', false);
        $response->assertDontSee('id="cp-results-modal"', false);
        $response->assertSee('Open for voting');
    }

    /**
     * @param  list<int>  $linked
     * @param  list<string>  $labels
     * @return array<string, mixed>
     */
    private function candidate(int $number, array $linked = [], array $labels = []): array
    {
        return [
            'id' => $number,
            'title' => "PR {$number}",
            'url' => "https://github.com/magento/magento2/pull/{$number}",
            'author' => 'jane',
            'created_at' => '2026-01-01T00:00:00Z',
            'thumbs_up_count' => 1,
            'linked_issues' => $linked,
            'labels' => $labels,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $sources  `_source` of each page hit
     * @param  list<array<string, mixed>>  $requests  Collects every page search request.
     * @param  list<string>  $prLabels  Area/Component labels on the candidates themselves (filter options).
     * @param  array<int, list<string>>  $issues  Linked issue number => labels in the issues index.
     * @param  list<string>  $authors  Candidate authors (author filter options).
     */
    private function bindClient(array $sources, ?int $total = null, array &$requests = [], array $prLabels = [], array $issues = [], array $authors = []): void
    {
        $buckets = static fn (array $keys): array => ['buckets' => array_map(static fn ($k): array => ['key' => $k], $keys)];

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(static function (array $params) use ($sources, $total, &$requests, $prLabels, $issues, $authors, $buckets): array {
            if (isset($params['body']['aggs']['spellings'])) {
                return ['aggregations' => ['spellings' => ['buckets' => []]]];
            }

            if (str_ends_with($params['index'], 'github-issues')) {
                return ['hits' => ['hits' => array_map(
                    static fn (int $id, array $labels): array => ['_id' => (string) $id, '_source' => ['labels' => $labels]],
                    array_keys($issues),
                    $issues,
                )]];
            }

            if (isset($params['body']['aggs'])) {
                return ['aggregations' => [
                    'linked_issues' => $buckets(array_keys($issues)),
                    'pr_labels' => $buckets($prLabels),
                    'authors' => $buckets($authors),
                ]];
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
