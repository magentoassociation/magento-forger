<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertSee('Community-picked PRs');
        $response->assertSee('Exclude orphan plugins from code generation');
        $response->assertSee('href="https://github.com/magento/magento2/pull/39812" target="_blank" rel="noopener"', false);
        $response->assertSee('aria-label="Vote for #39812 on GitHub (8 👍)"', false);
        $response->assertSee('href="https://github.com/'.config('github.repo').'/issues/39790"', false);
        $response->assertSee('href="https://github.com/swnsma"', false);
        $response->assertSee('3 months ago');
    }

    public function testIsPublic(): void
    {
        $this->bindClient([]);

        $this->get(route('prs.communityPicked'))->assertOk();
    }

    public function testPassesPageToQueryAndPaginates(): void
    {
        $requests = [];
        $this->bindClient([$this->candidate(1)], total: 120, requests: $requests);

        $response = $this->get('/prs/community-picked?page=2');

        $response->assertOk();
        $this->assertSame(50, $requests[0]['body']['from']);
        $response->assertSee('community-picked?page=3', false);
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
        $response->assertSee('area=Area%3A%20Checkout&amp;component=Component%3A%20Quote&amp;page=2', false);
    }

    public function testKeepsASharedFilterSelectableWhenNoCandidateCarriesIt(): void
    {
        $this->bindClient([]);

        $response = $this->get('/prs/community-picked?area='.urlencode('Area: Gone'));

        $response->assertSee('<option value="Area: Gone" selected>Gone</option>', false);
        $response->assertSee('No candidates match these filters.');
        $response->assertSee('>Clear</a>', false);
    }

    public function testRejectsLabelsOutsideTheirPrefix(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?area='.urlencode('Component: Quote'))->assertSessionHasErrors('area');
        $this->get('/prs/community-picked?component='.urlencode('Priority: P1'))->assertSessionHasErrors('component');
        $this->get('/prs/community-picked?area[]=x')->assertSessionHasErrors('area');
    }

    public function testRejectsInvalidPage(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked?page=abc')->assertSessionHasErrors('page');
        $this->get('/prs/community-picked?page=201')->assertSessionHasErrors('page');
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

    public function testIsLinkedFromPrsMenuAndFooter(): void
    {
        $this->bindClient([]);

        $this->get('/prs/community-picked')
            ->assertSee('href="'.route('prs.communityPicked').'">Community Picked</a>', false)
            ->assertSee('href="'.route('prs.communityPicked').'">Community-picked PRs</a>', false);
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
     */
    private function bindClient(array $sources, ?int $total = null, array &$requests = [], array $prLabels = [], array $issues = []): void
    {
        $buckets = static fn (array $keys): array => ['buckets' => array_map(static fn ($k): array => ['key' => $k], $keys)];

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(static function (array $params) use ($sources, $total, &$requests, $prLabels, $issues, $buckets): array {
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
