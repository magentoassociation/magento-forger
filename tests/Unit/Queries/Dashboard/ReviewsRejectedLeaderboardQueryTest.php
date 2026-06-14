<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Queries\Dashboard;

use App\DataTransferObjects\Dashboard\ContributorCount;
use App\Queries\Dashboard\ReviewsRejectedLeaderboardQuery;
use Carbon\Carbon;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class ReviewsRejectedLeaderboardQueryTest extends TestCase
{
    private Client $client;

    private ReviewsRejectedLeaderboardQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(Client::class);
        $this->query = new ReviewsRejectedLeaderboardQuery($this->client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_contributors_sorted_by_count(): void
    {
        $this->client->shouldReceive('search')->once()->andReturn([
            'aggregations' => [
                'by_contributor' => [
                    'buckets' => [
                        ['key' => 'carol', 'doc_count' => 6],
                        ['key' => 'dave', 'doc_count' => 2],
                    ],
                ],
            ],
        ]);

        $result = $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ContributorCount::class, $result[0]);
        $this->assertSame('carol', $result[0]->login);
        $this->assertSame(6, $result[0]->count);
    }

    public function test_returns_empty_array_when_no_buckets(): void
    {
        $this->client->shouldReceive('search')->once()->andReturn([
            'aggregations' => ['by_contributor' => ['buckets' => []]],
        ]);

        $result = $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertSame([], $result);
    }

    public function test_query_filters_on_changes_requested_state_and_submitted_at(): void
    {
        $capturedParams = null;
        $this->client->shouldReceive('search')->once()->withArgs(function (array $params) use (&$capturedParams) {
            $capturedParams = $params;

            return true;
        })->andReturn(['aggregations' => ['by_contributor' => ['buckets' => []]]]);

        $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $filters = $capturedParams['body']['query']['bool']['filter'];
        $this->assertTrue(collect($filters)->contains(fn ($f) => ($f['term']['state.keyword'] ?? null) === 'CHANGES_REQUESTED'));
        $this->assertTrue(collect($filters)->contains(fn ($f) => isset($f['range']['submitted_at'])));
    }

    public function test_query_targets_pr_reviews_index(): void
    {
        $capturedParams = null;
        $this->client->shouldReceive('search')->once()->withArgs(function (array $params) use (&$capturedParams) {
            $capturedParams = $params;

            return true;
        })->andReturn(['aggregations' => ['by_contributor' => ['buckets' => []]]]);

        $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertStringContainsString('github-pr-reviews', $capturedParams['index']);
    }

    public function test_query_excludes_bots(): void
    {
        $capturedParams = null;
        $this->client->shouldReceive('search')->once()->withArgs(function (array $params) use (&$capturedParams) {
            $capturedParams = $params;

            return true;
        })->andReturn(['aggregations' => ['by_contributor' => ['buckets' => []]]]);

        $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $mustNot = $capturedParams['body']['query']['bool']['must_not'];
        $this->assertTrue(collect($mustNot)->contains(fn ($f) => ($f['wildcard']['author.keyword'] ?? null) === 'engcom-*'));
        $this->assertTrue(collect($mustNot)->contains(fn ($f) => ($f['term']['author.keyword'] ?? null) === 'github-actions[bot]'));
    }
}
