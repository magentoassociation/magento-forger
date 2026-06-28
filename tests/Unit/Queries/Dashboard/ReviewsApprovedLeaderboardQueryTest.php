<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Queries\Dashboard;

use App\DataTransferObjects\Dashboard\ContributorCount;
use App\Queries\Dashboard\ReviewsApprovedLeaderboardQuery;
use Carbon\Carbon;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class ReviewsApprovedLeaderboardQueryTest extends TestCase
{
    private Client $client;

    private ReviewsApprovedLeaderboardQuery $query;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(Client::class);
        $this->query = new ReviewsApprovedLeaderboardQuery($this->client);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testReturnsContributorsSortedByCount(): void
    {
        $this->client->shouldReceive('search')->once()->andReturn([
            'aggregations' => [
                'by_contributor' => [
                    'buckets' => [
                        ['key' => 'alice', 'doc_count' => 8],
                        ['key' => 'bob', 'doc_count' => 3],
                    ],
                ],
            ],
        ]);

        $result = $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertCount(2, $result);
        $this->assertInstanceOf(ContributorCount::class, $result[0]);
        $this->assertSame('alice', $result[0]->login);
        $this->assertSame(8, $result[0]->count);
        $this->assertSame('bob', $result[1]->login);
        $this->assertSame(3, $result[1]->count);
    }

    public function testReturnsEmptyArrayWhenNoBuckets(): void
    {
        $this->client->shouldReceive('search')->once()->andReturn([
            'aggregations' => ['by_contributor' => ['buckets' => []]],
        ]);

        $result = $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertSame([], $result);
    }

    public function testQueryFiltersOnApprovedStateAndSubmittedAt(): void
    {
        $capturedParams = null;
        $this->client->shouldReceive('search')->once()->withArgs(function (array $params) use (&$capturedParams) {
            $capturedParams = $params;

            return true;
        })->andReturn(['aggregations' => ['by_contributor' => ['buckets' => []]]]);

        $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $filters = $capturedParams['body']['query']['bool']['filter'];
        $this->assertTrue(collect($filters)->contains(fn ($f) => ($f['term']['state.keyword'] ?? null) === 'APPROVED'));
        $this->assertTrue(collect($filters)->contains(fn ($f) => isset($f['range']['submitted_at'])));
    }

    public function testQueryTargetsPrReviewsIndex(): void
    {
        $capturedParams = null;
        $this->client->shouldReceive('search')->once()->withArgs(function (array $params) use (&$capturedParams) {
            $capturedParams = $params;

            return true;
        })->andReturn(['aggregations' => ['by_contributor' => ['buckets' => []]]]);

        $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $this->assertStringContainsString('github-pr-reviews', $capturedParams['index']);
    }

    public function testQueryExcludesBots(): void
    {
        $capturedParams = null;
        $this->client->shouldReceive('search')->once()->withArgs(function (array $params) use (&$capturedParams) {
            $capturedParams = $params;

            return true;
        })->andReturn(['aggregations' => ['by_contributor' => ['buckets' => []]]]);

        $this->query->execute(Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

        $mustNot = $capturedParams['body']['query']['bool']['must_not'];
        $this->assertTrue(
            collect($mustNot)->contains(fn ($f) => ($f['wildcard']['author.keyword'] ?? null) === 'engcom-*')
        );
        $this->assertTrue(
            collect($mustNot)->contains(fn ($f) => ($f['term']['author.keyword'] ?? null) === 'github-actions[bot]')
        );
    }
}
