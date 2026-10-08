<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Services\Search\OpenSearchService;
use OpenSearch\Client;

class OpenLabelsByIssueQuery
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<string, list<array{label: string, count: int}>>
     */
    public function execute(): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix('github-issues'),
            'body' => [
                'size' => 0,
                'query' => [
                    'term' => ['is_open' => true],
                ],
                'aggs' => [
                    'by_label' => [
                        'terms' => [
                            'field' => 'labels.keyword',
                            'order' => ['_key' => 'asc'],
                            'size' => 1000,
                        ],
                    ],
                ],
            ],
        ]);

        $nestedLabels = [];
        foreach ($response['aggregations']['by_label']['buckets'] as $bucket) {
            $parts = explode(':', $bucket['key'], 2);
            $prefix = count($parts) > 1 ? trim($parts[0]) : 'no_prefix';
            $nestedLabels[$prefix][] = [
                'label' => $bucket['key'],
                'count' => $bucket['doc_count'],
            ];
        }
        ksort($nestedLabels);

        return $nestedLabels;
    }
}
