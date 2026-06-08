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

class LeaderboardByMonthQuery
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<int|string, list<CompanyPoints>>
     */
    public function execute(int $year): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix('points'),
            'body' => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'filter' => [
                            'script' => [
                                'script' => [
                                    'source' => "doc['interaction_date'].value.getYear() == params.year",
                                    'lang' => 'painless',
                                    'params' => ['year' => $year],
                                ],
                            ],
                        ],
                    ],
                ],
                'aggs' => [
                    'by_month' => [
                        'terms' => [
                            'script' => [
                                'source' => "doc['interaction_date'].value.getMonthValue()",
                                'lang' => 'painless',
                            ],
                            'size' => 12,
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
        foreach ($response['aggregations']['by_month']['buckets'] as $bucket) {
            $companies = [];
            foreach ($bucket['by_company']['buckets'] as $companyBucket) {
                $companies[] = new CompanyPoints(
                    name: $companyBucket['key'],
                    points: (int) $companyBucket['total_points']['value'],
                );
            }
            $result[$bucket['key']] = $companies;
        }
        ksort($result);

        return $result;
    }
}
