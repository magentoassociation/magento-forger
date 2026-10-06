<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Helpers\GitHubLinkHelper;
use App\Services\GitHub\GitHubIssueService;
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

    public function testReadyToCodeUsesAllLabeledIssuesWhileUnclaimedOnlyIsOff(): void
    {
        config(['homepage.paths.0.unclaimed_only' => false]);
        $this->bindClient($this->prAggregations(), [['key' => 'Issue: Ready for Work', 'doc_count' => 20]]);
        $issues = Mockery::mock(GitHubIssueService::class);
        $issues->shouldNotReceive('searchIssueCount');
        $this->app->instance(GitHubIssueService::class, $issues);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(e(GitHubLinkHelper::issueLabelUrl('Issue: Ready for Work')), false);
        $response->assertDontSee('no%3Aassignee', false);
        $response->assertSee('20 open');
    }

    public function testReadyToCodeLinkAndCountExcludeClaimedIssuesWhenUnclaimedOnlyIsOn(): void
    {
        config(['homepage.paths.0.unclaimed_only' => true]);
        $this->bindClient($this->prAggregations(), [
            ['key' => 'Issue: Ready for Work', 'doc_count' => 20],
            ['key' => 'Area: Framework', 'doc_count' => 221],
        ]);
        $issues = Mockery::mock(GitHubIssueService::class);
        $issues->shouldReceive('searchIssueCount')
            ->once()
            ->with('repo:'.config('github.repo').' is:issue is:open label:"Issue: Ready for Work" no:assignee -linked:pr')
            ->andReturn(3);
        $this->app->instance(GitHubIssueService::class, $issues);

        $response = $this->get(route('home'));

        $response->assertOk();
        $readyUrl = e(GitHubLinkHelper::issueLabelUrl('Issue: Ready for Work', unclaimed: true));
        $this->assertStringContainsString('no%3Aassignee+-linked%3Apr', $readyUrl);
        $response->assertSee($readyUrl, false);
        $response->assertSee('3 open');
        $response->assertDontSee('20 open');
        // Area tiles are unaffected by the flag.
        $response->assertSee(e(GitHubLinkHelper::issueLabelUrl('Area: Framework')), false);
        $response->assertDontSee(e(GitHubLinkHelper::issueLabelUrl('Area: Framework', unclaimed: true)), false);
    }

    public function testUnclaimedCountFailureDropsPillButKeepsLink(): void
    {
        config(['homepage.paths.0.unclaimed_only' => true]);
        $this->bindClient($this->prAggregations(), [['key' => 'Issue: Ready for Work', 'doc_count' => 20]]);
        $issues = Mockery::mock(GitHubIssueService::class);
        $issues->shouldReceive('searchIssueCount')->andThrow(new RuntimeException('github down'));
        $this->app->instance(GitHubIssueService::class, $issues);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(e(GitHubLinkHelper::issueLabelUrl('Issue: Ready for Work', unclaimed: true)), false);
        $response->assertDontSee('20 open');
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
     * Bind a mocked OpenSearch client that answers the PR aggregation and the label
     * aggregation independently, keyed by index.
     *
     * @param  array<string, mixed>  $prResult  Response for the github-pull-requests index.
     * @param  list<array{key: string, doc_count: int}>|\Throwable  $labelBuckets  Buckets for the
     *                                                                             github-issues by_label
     *                                                                             aggregation, or a throwable
     *                                                                             to simulate failure.
     */
    private function bindClient(array $prResult, array|\Throwable $labelBuckets): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(
            static function (array $params) use ($prResult, $labelBuckets) {
                // str_contains, not === : getIndexWithPrefix() prepends a configurable prefix.
                if (str_contains($params['index'], 'github-issues')) {
                    if ($labelBuckets instanceof \Throwable) {
                        throw $labelBuckets;
                    }

                    return ['aggregations' => ['by_label' => ['buckets' => $labelBuckets]]];
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
}
