<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace Tests\Unit\Services\Leaderboard;

use App\Services\Leaderboard\AuthorCompanyReader;
use App\Services\Search\OpenSearchService;
use Mockery;
use OpenSearch\Client;
use Tests\TestCase;

class AuthorCompanyReaderTest extends TestCase
{
    /**
     * Build a composite-aggregation response with one bucket per author.
     *
     * @param  array<string, array{company: string, updated_at: string}>  $byLogin
     * @return array<string, mixed>
     */
    private function response(array $byLogin): array
    {
        $buckets = [];
        foreach ($byLogin as $login => $row) {
            $buckets[] = [
                'key' => ['author' => $login],
                'latest' => ['hits' => ['hits' => [
                    ['_source' => ['author_company' => $row['company'], 'updated_at' => $row['updated_at']]],
                ]]],
            ];
        }

        return ['aggregations' => ['authors' => ['buckets' => $buckets, 'after_key' => null]]];
    }

    /**
     * Mock a Client returning $prDocs for the PR index and $issueDocs for the
     * issues index, keyed by which index the search call targets.
     *
     * @param  array<string, array{company: string, updated_at: string}>  $prDocs
     * @param  array<string, array{company: string, updated_at: string}>  $issueDocs
     */
    private function reader(array $prDocs, array $issueDocs): AuthorCompanyReader
    {
        $prIndex = OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_PULL_REQUESTS_INDEX);

        $client = Mockery::mock(Client::class);
        $client->shouldReceive('search')
            ->andReturnUsing(fn (array $params): array => $params['index'] === $prIndex
                ? $this->response($prDocs)
                : $this->response($issueDocs));

        return new AuthorCompanyReader($client);
    }

    public function testNewerIssueCompanyOverridesOlderPrCompany(): void
    {
        $reader = $this->reader(
            prDocs: ['jane' => ['company' => 'OldCo', 'updated_at' => '2026-01-01T00:00:00Z']],
            issueDocs: ['jane' => ['company' => 'NewCo', 'updated_at' => '2026-05-01T00:00:00Z']],
        );

        $this->assertSame(['jane' => 'NewCo'], $reader->read());
    }

    public function testOlderIssueCompanyDoesNotOverrideNewerPrCompany(): void
    {
        $reader = $this->reader(
            prDocs: ['jane' => ['company' => 'NewCo', 'updated_at' => '2026-05-01T00:00:00Z']],
            issueDocs: ['jane' => ['company' => 'OldCo', 'updated_at' => '2026-01-01T00:00:00Z']],
        );

        $this->assertSame(['jane' => 'NewCo'], $reader->read());
    }

    public function testMergesAuthorsPresentInOnlyOneIndex(): void
    {
        $reader = $this->reader(
            prDocs: ['jane' => ['company' => 'Acme', 'updated_at' => '2026-01-01T00:00:00Z']],
            issueDocs: ['bob' => ['company' => 'Globex', 'updated_at' => '2026-02-01T00:00:00Z']],
        );

        $this->assertSame(['jane' => 'Acme', 'bob' => 'Globex'], $reader->read());
    }
}
