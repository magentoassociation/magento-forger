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
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ByMonthAllTimeChartTest extends TestCase
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

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function pages(): array
    {
        return [
            'issues' => ['issues.byMonth', 'github-issues', 'Issues', 'issues'],
            'prs' => ['prs.byMonth', 'github-pull-requests', 'Pull requests', 'PRs'],
        ];
    }

    #[DataProvider('pages')]
    public function testPageRendersAllTimeChartFromItsOwnIndex(string $route, string $index, string $title, string $noun): void
    {
        $queried = [];
        $this->bindClient($this->allTimeAggregation(), $queried);

        $response = $this->get(route($route));

        $stats = ['2014-12' => ['opened' => 1200, 'closed' => 0], '2026-09' => ['opened' => 34, 'closed' => 1100]];
        $response->assertOk();
        $response->assertViewHas('allTimeStats', $stats);
        $response->assertSee('Opened and closed, all time');
        $response->assertSee("All {$noun} opened and closed since the first month, by quarter.", false);
        $response->assertSeeInOrder([$title, 'Dec 2014 – Sep 2026', '1,234', 'opened', '1,100', 'closed'], false);
        $response->assertSee(
            "aria-label=\"{$title} opened and closed per quarter, Dec 2014 to Sep 2026: 1,234 opened, 1,100 closed\"",
            false,
        );
        $response->assertSee('data-stats="'.e(json_encode($stats)).'"', false);
        $this->assertNotEmpty(array_filter($queried, static fn (string $i): bool => str_contains($i, $index)));
    }

    #[DataProvider('pages')]
    public function testPageHidesAllTimeChartWhenSearchFails(string $route): void
    {
        $queried = [];
        $this->bindClient(new RuntimeException('opensearch timeout'), $queried);

        $response = $this->get(route($route));

        $response->assertOk();
        $response->assertViewHas('allTimeStats', null);
        $response->assertDontSee('<h2 class="bm-alltime-title">', false);
        $response->assertDontSee('id="allTimeChart"', false);
    }

    #[DataProvider('pages')]
    public function testPageHidesAllTimeChartWhenThereIsNoHistory(string $route): void
    {
        $queried = [];
        $this->bindClient(['aggregations' => []], $queried);

        $response = $this->get(route($route));

        $response->assertOk();
        $response->assertViewHas('allTimeStats', []);
        $response->assertDontSee('id="allTimeChart"', false);
    }

    #[DataProvider('pages')]
    public function testPageNoLongerRendersOrQueriesTheAgeChart(string $route): void
    {
        $ageQueries = 0;
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(function (array $params) use (&$ageQueries) {
            if (isset($params['body']['aggs']['monthly_closures'])) {
                $ageQueries++;
            }

            return ['aggregations' => ['by_year' => ['buckets' => []]]];
        });
        $this->app->instance(Client::class, $client);

        $response = $this->get(route($route));

        $response->assertOk();
        $response->assertViewMissing('ageStats');
        $response->assertDontSee('Average age at close');
        $this->assertSame(0, $ageQueries);
    }

    public function testPagesLiveUnderKebabCasePathsAndOldPathsAreGone(): void
    {
        $this->assertSame(url('issues/by-month'), route('issues.byMonth'));
        $this->assertSame(url('prs/by-month'), route('prs.byMonth'));

        $this->get('/issuesByMonth')->assertNotFound();
        $this->get('/prsByMonth')->assertNotFound();
    }

    /**
     * Bind a mocked OpenSearch client: the opened/closed aggregation gets $result, the
     * open-items timeline gets an empty year histogram.
     *
     * @param  array<string, mixed>|\Throwable  $result  Response for the all-time aggregation, or a
     *                                                   throwable to simulate failure.
     * @param  list<string>  $queried  Collects the index of every all-time query.
     */
    private function bindClient(array|\Throwable $result, array &$queried): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(
            static function (array $params) use ($result, &$queried) {
                $aggs = array_keys($params['body']['aggs'] ?? []);
                if (! array_filter($aggs, static fn (string $name): bool => str_ends_with($name, '_opened_per_month'))) {
                    return ['aggregations' => ['by_year' => ['buckets' => []]]];
                }

                $queried[] = $params['index'];
                if ($result instanceof \Throwable) {
                    throw $result;
                }

                // Answer under whichever prefix the page asked for (prs_ / issues_).
                $prefix = str_replace('_opened_per_month', '', $aggs[0]);

                return ['aggregations' => array_combine(
                    array_map(static fn (string $k): string => $prefix.$k, array_keys($result['aggregations'] ?? [])),
                    array_values($result['aggregations'] ?? []),
                ) ?: []];
            }
        );

        $this->app->instance(Client::class, $client);
    }

    /**
     * Aggregation buckets keyed by suffix; bindClient() prefixes them for the page's index.
     *
     * @return array<string, mixed>
     */
    private function allTimeAggregation(): array
    {
        return [
            'aggregations' => [
                '_opened_per_month' => ['buckets' => [
                    ['key_as_string' => '2014-12', 'doc_count' => 1200],
                    ['key_as_string' => '2026-09', 'doc_count' => 34],
                ]],
                '_closed_per_month' => ['buckets' => [
                    ['key_as_string' => '2026-09', 'doc_count' => 1100],
                ]],
            ],
        ];
    }
}
