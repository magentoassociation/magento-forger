<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\DataTransferObjects\Leaderboard\Action;
use App\Services\Leaderboard\ContributionDetailReader;
use Carbon\Carbon;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class ContributionDetailReaderTest extends TestCase
{
    /**
     * Build a hit document carrying every date field the reader may parse, so a
     * single fixture can stand in for any search type without tripping on a
     * missing key.
     *
     * @return array{_source: array<string, mixed>}
     */
    private function prHit(int $n): array
    {
        return ['_source' => [
            'title' => "PR {$n}",
            'url' => "https://example.test/{$n}",
            'created_at' => '2026-01-01T00:00:00Z',
            'merged_at' => '2026-01-02T00:00:00Z',
            'closed_at' => '2026-01-03T00:00:00Z',
            'additions' => 0,
            'deletions' => 0,
        ]];
    }

    public function testPagesPastTheFirst500AndKeepsAllItems(): void
    {
        $from = Carbon::parse('2025-06-01T00:00:00Z');
        $to = Carbon::parse('2026-06-01T00:00:00Z');

        // First PR-opened search: a full page (500) then a partial page (3),
        // so pagination must issue a second request and stop on the short page.
        $fullPage = ['hits' => ['hits' => array_map(fn (int $n): array => $this->prHit($n), range(1, 500))]];
        $partialPage = ['hits' => ['hits' => array_map(fn (int $n): array => $this->prHit($n), range(501, 503))]];
        $emptyPage = ['hits' => ['hits' => []]];

        $client = Mockery::mock(Client::class);
        $offsets = [];
        $client->shouldReceive('search')
            ->andReturnUsing(function (array $params) use (&$offsets, $fullPage, $partialPage, $emptyPage): array {
                $index = $params['index'];
                $offset = $params['body']['from'];

                // Only the PR-opened query (created_at sort, no extra state filter)
                // returns two pages; every other type returns one empty page.
                $isPrOpened = str_contains($index, 'pull') && ! isset($params['body']['query']['bool']['filter'][2]);

                if (! $isPrOpened) {
                    return $emptyPage;
                }

                $offsets[] = $offset;

                return $offset === 0 ? $fullPage : $partialPage;
            });

        $reader = new ContributionDetailReader($client);
        $items = $reader->readForLogin('jane', $from, $to);

        $prOpened = array_values(array_filter($items, fn ($item): bool => $item->action === Action::PR_OPENED));

        // 500 + 3 = 503 retrieved, proving the second page was fetched.
        $this->assertCount(503, $prOpened);
        $this->assertSame([0, 500], $offsets);
    }

    public function testSingleShortPageIssuesNoSecondRequest(): void
    {
        $from = Carbon::parse('2025-06-01T00:00:00Z');
        $to = Carbon::parse('2026-06-01T00:00:00Z');

        $response = ['hits' => ['hits' => [$this->prHit(1)]]];
        $offsets = [];
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')
            ->andReturnUsing(function (array $params) use (&$offsets, $response): array {
                $offsets[] = $params['body']['from'];

                return $response;
            });

        $reader = new ContributionDetailReader($client);
        $items = $reader->readForLogin('jane', $from, $to);

        $this->assertNotEmpty($items);
        // Each search type returned a short page (1 < PAGE_SIZE), so none paged
        // past the first request: every offset is 0, never a second page at 500.
        $this->assertSame([0], array_values(array_unique($offsets)));
    }
}
