<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Helpers\GitHubLinkHelper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use OpenSearch\Client;
use RuntimeException;
use Tests\TestCase;

class WelcomeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function testHomepageRendersPathsAndAreaTilesWithLiveCounts(): void
    {
        $this->bindClient($this->prAggregations(), [
            ['key' => 'Issue: Ready for Work', 'doc_count' => 20],
            ['key' => 'Area: Framework', 'doc_count' => 221],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewIs('welcome');
        $response->assertSee('Start contributing');
        $response->assertSee('Ready to code');
        $response->assertSee('20 open');       // Ready for Work path pill
        $response->assertSee('Framework');     // area tile (prefix stripped)
        $response->assertSee('221 open');      // area tile pill
        $response->assertSee('aria-current="page"', false); // current nav item
    }

    public function testHomepageRendersMomentumChartsWithMonthlyPrAndIssueCounts(): void
    {
        $this->bindClient($this->prAggregations(), []);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewHas('prStats', ['2026-01' => ['opened' => 5, 'closed' => 3]]);
        $response->assertViewHas('issueStats', ['2026-01' => ['opened' => 8, 'closed' => 6]]);
        $response->assertViewHas('dataMissing', false);
        $response->assertSee('<h2 class="hp-h2">Momentum</h2>', false);
        $response->assertSee('id="prChart"', false);
        $response->assertSee('id="issueChart"', false);
        // The section closes on the hero CTA, repeated once.
        $this->assertSame(2, substr_count($response->getContent(), 'Find an issue to work on →'));
    }

    public function testMomentumCardsChartTheLastTwelveMonthsAndLinkToFullHistory(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15'));
        $this->bindClient([
            'aggregations' => [
                'prs_opened_per_month' => ['buckets' => [
                    ['key_as_string' => '2014-12', 'doc_count' => 1200],
                    ['key_as_string' => '2026-09', 'doc_count' => 34],
                ]],
                'prs_closed_per_month' => ['buckets' => [
                    ['key_as_string' => '2026-09', 'doc_count' => 1100],
                ]],
            ],
        ], []);

        $response = $this->get(route('home'));

        $response->assertOk();
        // Nov 2025 – Oct 2026: the Dec 2014 bucket is outside the window, so only Sep 2026 counts.
        $response->assertSeeInOrder(['Pull requests', 'Last 12 months', '34', 'opened', '1,100', 'closed'], false);
        $response->assertSee(
            'aria-label="Pull requests opened and closed per month, last 12 months: 34 opened, 1,100 closed"',
            false,
        );
        $response->assertSee(e('"label":"Sep 2026","short":"Sep","opened":34,"closed":1100'), false);
        $response->assertSeeInOrder(['<span>Nov</span>', '<span>Dec</span>', '<span>Sep</span>', '<span>Oct</span>'], false);
        // Footer: all-time opened since the first month, linking to the By Month page.
        $response->assertSee('href="'.route('prs.byMonth').'" aria-label="Full history: Pull requests by month"', false);
        $response->assertSee('<b>1,234</b> opened since Dec 2014', false);
        $response->assertSee('href="'.route('issues.byMonth').'" aria-label="Full history: Issues by month"', false);
    }

    public function testMomentumMonthsWithNoBucketCountAsZero(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15'));
        $this->bindClient(['aggregations' => []], [], ['aggregations' => []]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('aria-label="Pull requests opened and closed per month, last 12 months: 0 opened, 0 closed"', false);
        $response->assertSee(e('"label":"Oct 2026","short":"Oct","opened":0,"closed":0'), false);
        // No history at all: no "since" line, but the link stays.
        $response->assertDontSee('opened since', false);
        $response->assertSee('aria-label="Full history: Pull requests by month"', false);
    }

    public function testHomepageHidesOnlyPrChartWhenPrSearchFails(): void
    {
        $this->bindClient(new RuntimeException('opensearch timeout'), [
            ['key' => 'Issue: Ready for Work', 'doc_count' => 20],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewHas('prStats', null);
        $response->assertSee('Ready to code');    // rest of the page still renders
        $response->assertSee('<h2 class="hp-h2">Momentum</h2>', false);
        $response->assertDontSee('id="prChart"', false);
        $response->assertSee('id="issueChart"', false);
    }

    public function testHomepageHidesOnlyIssueChartWhenIssueSearchFails(): void
    {
        $this->bindClient($this->prAggregations(), [], new RuntimeException('opensearch timeout'));

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewHas('issueStats', null);
        $response->assertSee('id="prChart"', false);
        $response->assertDontSee('id="issueChart"', false);
    }

    public function testHomepageHidesMomentumSectionWhenBothSearchesFail(): void
    {
        $this->bindClient(
            new RuntimeException('opensearch timeout'),
            [['key' => 'Issue: Ready for Work', 'doc_count' => 20]],
            new RuntimeException('opensearch timeout'),
        );

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Ready to code');    // rest of the page still renders
        $response->assertDontSee('<h2 class="hp-h2">Momentum</h2>', false);
        $response->assertDontSee('<canvas', false);
        $response->assertDontSee('class="chart-card chart-card--momentum"', false);
    }

    public function testReadyToCodeUsesAllLabeledIssuesWhileUnclaimedOnlyIsOff(): void
    {
        config(['homepage.paths.0.unclaimed_only' => false]);
        $this->bindClient($this->prAggregations(), [['key' => 'Issue: Ready for Work', 'doc_count' => 20]]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(e(GitHubLinkHelper::issueLabelUrl('Issue: Ready for Work')), false);
        $response->assertDontSee('no%3Aassignee', false);
        $response->assertSee('20 open');
    }

    public function testReadyToCodeLinksToUnclaimedIssuesWithoutCountWhenUnclaimedOnlyIsOn(): void
    {
        config(['homepage.paths.0.unclaimed_only' => true]);
        $this->bindClient($this->prAggregations(), [
            ['key' => 'Issue: Ready for Work', 'doc_count' => 20],
            ['key' => 'Area: Framework', 'doc_count' => 221],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $readyUrl = e(GitHubLinkHelper::issueLabelUrl('Issue: Ready for Work', unclaimed: true));
        $this->assertStringContainsString('no%3Aassignee+-linked%3Apr', $readyUrl);
        $response->assertSee($readyUrl, false);
        // The all-labeled count would overstate the unclaimed pool, so no pill renders.
        $response->assertDontSee('20 open');
        // Area tiles are unaffected by the flag.
        $response->assertSee('221 open');
        $response->assertSee(e(GitHubLinkHelper::issueLabelUrl('Area: Framework')), false);
        $response->assertDontSee(e(GitHubLinkHelper::issueLabelUrl('Area: Framework', unclaimed: true)), false);
    }

    public function testHomepageSurvivesLabelCountFailureAndDropsPills(): void
    {
        // PR chart succeeds; the label-count aggregation throws. The page must still render
        // (cards intact) and simply omit the count pills.
        $this->bindClient($this->prAggregations(), new RuntimeException('opensearch down'));

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Ready to code');   // card still rendered
        $response->assertDontSee('20 open');     // no pill when counts are unavailable
    }

    public function testHeroCtaOpensGitHubInNamedWindow(): void
    {
        $this->bindClient($this->prAggregations(), []);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '#href="https://github\.com/[^"]+"\s+target="magentoForgerGitHub" rel="noopener"\s+class="hp-cta-primary"#',
            $response->getContent(),
        );
    }

    public function testHeroCtaFallbackToLeaderboardStaysInCurrentTab(): void
    {
        config(['homepage.paths' => []]);
        $this->bindClient($this->prAggregations(), []);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('leaderboard.show', ['board' => 'contributor']), '#').'"\s+class="hp-cta-primary"#',
            $response->getContent(),
        );
    }

    public function testClaimAnIssueLinkOpensGitHubInNamedWindow(): void
    {
        $this->bindClient($this->prAggregations(), []);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '#href="https://github\.com/[^"]+"\s+target="magentoForgerGitHub" rel="noopener"\s*>Claim an issue</a>#',
            $response->getContent(),
        );
    }

    public function testClaimAnIssueFallbackToLeaderboardStaysInCurrentTab(): void
    {
        config(['homepage.paths' => []]);
        $this->bindClient($this->prAggregations(), []);

        $response = $this->get(route('home'));

        $response->assertOk();
        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('leaderboard.show', ['board' => 'contributor']), '#').'"\s*>Claim an issue</a>#',
            $response->getContent(),
        );
    }

    public function testHeroHasSingleCtaForGuests(): void
    {
        $this->bindClient($this->prAggregations(), []);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Find an issue to work on →');
        // Signing in lives in the header only; the hero carries no second button.
        $response->assertDontSee('hp-cta-secondary', false);
        $this->assertSame(1, substr_count($response->getContent(), 'Login with GitHub'));
    }

    /**
     * Bind a mocked OpenSearch client that answers the PR chart, issue chart and label
     * aggregations independently, keyed by index and aggregation name.
     *
     * @param  array<string, mixed>|\Throwable  $prResult  Response for the github-pull-requests index,
     *                                                     or a throwable to simulate failure.
     * @param  list<array{key: string, doc_count: int}>|\Throwable  $labelBuckets  Buckets for the
     *                                                                             github-issues by_label
     *                                                                             aggregation, or a throwable
     *                                                                             to simulate failure.
     * @param  array<string, mixed>|\Throwable|null  $issueResult  Response for the issue chart aggregation
     *                                                             (defaults to issueAggregations()), or a
     *                                                             throwable to simulate failure.
     */
    private function bindClient(
        array|\Throwable $prResult,
        array|\Throwable $labelBuckets,
        array|\Throwable|null $issueResult = null,
    ): void {
        $issueResult ??= $this->issueAggregations();
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(
            static function (array $params) use ($prResult, $labelBuckets, $issueResult) {
                if (isset($params['body']['aggs']['issues_opened_per_month'])) {
                    if ($issueResult instanceof \Throwable) {
                        throw $issueResult;
                    }

                    return $issueResult;
                }

                // str_contains, not === : getIndexWithPrefix() prepends a configurable prefix.
                if (str_contains($params['index'], 'github-issues')) {
                    if ($labelBuckets instanceof \Throwable) {
                        throw $labelBuckets;
                    }

                    return ['aggregations' => ['by_label' => ['buckets' => $labelBuckets]]];
                }

                if ($prResult instanceof \Throwable) {
                    throw $prResult;
                }

                return $prResult;
            }
        );

        $this->app->instance(Client::class, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function prAggregations(): array
    {
        return [
            'aggregations' => [
                'prs_opened_per_month' => ['buckets' => [['key_as_string' => '2026-01', 'doc_count' => 5]]],
                'prs_closed_per_month' => ['buckets' => [['key_as_string' => '2026-01', 'doc_count' => 3]]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function issueAggregations(): array
    {
        return [
            'aggregations' => [
                'issues_opened_per_month' => ['buckets' => [['key_as_string' => '2026-01', 'doc_count' => 8]]],
                'issues_closed_per_month' => ['buckets' => [['key_as_string' => '2026-01', 'doc_count' => 6]]],
            ],
        ];
    }
}
