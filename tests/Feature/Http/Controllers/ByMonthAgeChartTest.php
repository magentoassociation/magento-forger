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

class ByMonthAgeChartTest extends TestCase
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
     * @return array<string, array{string, string, string}>
     */
    public static function pages(): array
    {
        return [
            'issues' => ['issues.issuesByMonth', 'github-issues', 'issues'],
            'prs' => ['prs.PRsByMonth', 'github-pull-requests', 'PRs'],
        ];
    }

    #[DataProvider('pages')]
    public function testPageRendersAgeChartFromItsOwnIndex(string $route, string $index, string $noun): void
    {
        $queried = [];
        $this->bindClient($this->ageAggregation(), $queried);

        $response = $this->get(route($route));

        $response->assertOk();
        $response->assertViewHas('ageStats', ['2026-01' => 12, '2026-02' => null]);
        $response->assertSee('Average age at close');
        $response->assertSee("averaged over the {$noun} closed each month", false);
        $response->assertSee('id="ageChart"', false);
        $response->assertSee('const ageStats = {"2026-01":12,"2026-02":null}', false);
        $this->assertNotEmpty(array_filter($queried, static fn (string $i): bool => str_contains($i, $index)));
    }

    #[DataProvider('pages')]
    public function testPageHidesAgeChartWhenAgeSearchFails(string $route): void
    {
        $queried = [];
        $this->bindClient(new RuntimeException('opensearch timeout'), $queried);

        $response = $this->get(route($route));

        $response->assertOk();
        $response->assertViewHas('ageStats', null);
        $response->assertDontSee('Average age at close');
        $response->assertDontSee('id="ageChart"', false);
        $response->assertDontSee('forgerBarChart', false);
    }

    #[DataProvider('pages')]
    public function testPageHidesAgeChartWhenNoItemsClosed(string $route): void
    {
        $queried = [];
        $this->bindClient(['aggregations' => ['monthly_closures' => ['buckets' => []]]], $queried);

        $response = $this->get(route($route));

        $response->assertOk();
        $response->assertViewHas('ageStats', []);
        $response->assertDontSee('id="ageChart"', false);
    }

    /**
     * Bind a mocked OpenSearch client: the age aggregation gets $ageResult, the open-items
     * timeline gets an empty year histogram.
     *
     * @param  array<string, mixed>|\Throwable  $ageResult  Response for the age aggregation, or a
     *                                                      throwable to simulate failure.
     * @param  list<string>  $queried  Collects the index of every age query.
     */
    private function bindClient(array|\Throwable $ageResult, array &$queried): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(
            static function (array $params) use ($ageResult, &$queried) {
                if (! isset($params['body']['aggs']['monthly_closures'])) {
                    return ['aggregations' => ['by_year' => ['buckets' => []]]];
                }

                $queried[] = $params['index'];
                if ($ageResult instanceof \Throwable) {
                    throw $ageResult;
                }

                return $ageResult;
            }
        );

        $this->app->instance(Client::class, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function ageAggregation(): array
    {
        return [
            'aggregations' => [
                'monthly_closures' => ['buckets' => [
                    ['key_as_string' => '2026-01', 'avg_days_open' => ['value' => 12.7]],
                    ['key_as_string' => '2026-02', 'avg_days_open' => ['value' => null]],
                ]],
            ],
        ];
    }
}
