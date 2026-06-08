<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\DataTransferObjects\Dashboard\CompanyPoints;
use App\Services\Search\OpenSearchService;
use OpenSearch\Client;

class LeaderboardByYearQuery
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<int|string, list<CompanyPoints>>
     */
    public function execute(): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix('points'),
            'body' => [
                'size' => 0,
                'aggs' => [
                    'by_year' => [
                        'terms' => [
                            'script' => [
                                'source' => "doc['interaction_date'].value.getYear()",
                                'lang' => 'painless',
                            ],
                            'size' => 100,
                            'order' => ['_key' => 'asc'],
                        ],
                        'aggs' => [
                            'by_company' => [
                                'terms' => [
                                    'field' => 'company_name.keyword',
                                    'size' => 1000,
                                    'order' => ['total_points' => 'desc'],
                                ],
                                'aggs' => [
                                    'total_points' => [
                                        'sum' => ['field' => 'points'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = [];
        foreach ($response['aggregations']['by_year']['buckets'] as $bucket) {
            $companies = [];
            foreach ($bucket['by_company']['buckets'] as $companyBucket) {
                $companies[] = new CompanyPoints(
                    name: $companyBucket['key'],
                    points: (int) $companyBucket['total_points']['value'],
                );
            }
            $result[$bucket['key']] = $companies;
        }
        krsort($result);

        return $result;
    }
}
