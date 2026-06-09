<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

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

    public function test_homepage_renders_paths_and_area_tiles_with_live_counts(): void
    {
        $this->bindClient($this->prAggregations(), [
            ['key' => 'Issue: Ready for Work', 'doc_count' => 20],
            ['key' => 'Area: Framework', 'doc_count' => 221],
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertViewIs('welcome');
        $response->assertSee('Choose how you want to help');
        $response->assertSee('Ready to code');
        $response->assertSee('20 open');       // Ready for Work path pill
        $response->assertSee('Framework');     // area tile (prefix stripped)
        $response->assertSee('221 open');      // area tile pill
    }

    public function test_homepage_survives_label_count_failure_and_drops_pills(): void
    {
        // PR chart succeeds; the label-count aggregation throws. The page must still render
        // (cards intact) and simply omit the count pills.
        $this->bindClient($this->prAggregations(), new RuntimeException('opensearch down'));

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Ready to code');   // card still rendered
        $response->assertDontSee('20 open');     // no pill when counts are unavailable
    }

    /**
     * Bind a mocked OpenSearch client that answers the PR aggregation and the label
     * aggregation independently, keyed by index.
     *
     * @param  array<string, mixed>  $prResult  Response for the github-pull-requests index.
     * @param  list<array{key: string, doc_count: int}>|\Throwable  $labelBuckets
     *                                                                             Buckets for the github-issues by_label aggregation, or a throwable to simulate failure.
     */
    private function bindClient(array $prResult, array|\Throwable $labelBuckets): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')->andReturnUsing(
            static function (array $params) use ($prResult, $labelBuckets) {
                if ($params['index'] === 'github-issues') {
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
