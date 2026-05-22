<?php
/**
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Search\QueryBuilder;
use App\DataTransferObjects\Search\Filter;
use App\DataTransferObjects\Search\FilterType;
use App\Services\Search\OpenSearchService;

class SearchPullRequests extends Command
{
    protected $signature = 'search:pull-requests';

    protected $description = 'Search OpenSearch for open GitHub Pull Requests';

    protected OpenSearchService $searchService;

    public function __construct(OpenSearchService $searchService)
    {
        parent::__construct();
        $this->searchService = $searchService;
    }

    public function handle(): int
    {
        $this->info('Building query...');

        $queryBuilder = new QueryBuilder();

        // Example filter: only open PRs
        $queryBuilder->addFilter(new Filter('state', FilterType::TERM, 'OPEN'));

        $queryBuilder->addSort([
            ['created_at' => ['order' => 'desc']]
        ]);

        $queryBuilder->setSize(5);

        // Optional: select only specific fields
        $queryBuilder->selectFields(['id', 'title', 'state', 'created_at', 'author']);

        $this->info('Executing search...');
        $results = $this->searchService->searchPRs($queryBuilder);

        $hits = $results['hits']['hits'] ?? [];

        if (empty($hits)) {
            $this->info('No pull requests found.');
            return 0;
        }

        $this->info('Results:');

        foreach ($hits as $hit) {
            $source = $hit['_source'] ?? [];
            $this->line(sprintf(
                "#%d: %s (%s) by %s - created %s",
                $source['id'] ?? 'N/A',
                $source['title'] ?? 'No Title',
                $source['state'] ?? 'unknown',
                $source['author'] ?? 'unknown',
                $source['created_at'] ?? 'unknown'
            ));
        }

        return 0;
    }
}
