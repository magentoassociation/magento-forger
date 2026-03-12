<?php

namespace App\Http\Controllers;

use App\Services\Search\OpenSearchService;
use Illuminate\View\View;
use OpenSearch\Client;

class LeaderboardController extends Controller
{
    public function index(Client $client): View
    {
        $params = [
            'index' => OpenSearchService::getIndexWithPrefix('points'),
            'body'  => [
                'size' => 0,
                'aggs' => [
                    'by_year' => [
                        'terms' => [
                            'script' => [
                                'source' => "doc['interaction_date'].value.getYear()",
                                'lang'   => 'painless'
                            ],
                            'size'  => 100,
                            'order' => [
                                '_key' => 'asc'
                            ]
                        ],
                        'aggs' => [
                            'by_company' => [
                                'terms' => [
                                    'field' => 'company_name.keyword',
                                    'size'  => 1000,
                                    'order' => [
                                        'total_points' => 'desc'
                                    ]
                                ],
                                'aggs' => [
                                    'total_points' => [
                                        'sum' => [
                                            'field' => 'points'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $result = $client->search($params);
        $dataToDisplay = [];
        $buckets = $result['aggregations']['by_year']['buckets'];

        foreach ($buckets as $bucket) {
            $yearlyData = [];
            foreach ($bucket['by_company']['buckets'] as $companyBucket) {
                $yearlyData[] = [
                    'name' => $companyBucket['key'],
                    'points' => (int)$companyBucket['total_points']['value'],
                ];
            }
            $dataToDisplay[$bucket['key']] = $yearlyData;
        }
        krsort($dataToDisplay);
        return view('leaderboard/leaderboard', ['data' => $dataToDisplay]);
    }

    public function showYear(Client $client, int $year): View
    {
        $params = [
            'index' => OpenSearchService::getIndexWithPrefix('points'),
            'body'  => [
                'size' => 0,
                'query' => [
                    'bool' => [
                        'filter' => [
                            'script' => [
                                'script' => [
                                    'source' => "doc['interaction_date'].value.getYear() == params.year",
                                    'lang'   => 'painless',
                                    'params' => [
                                        'year' => $year
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'aggs' => [
                    'by_month' => [
                        'terms' => [
                            'script' => [
                                'source' => "doc['interaction_date'].value.getMonthValue()",
                                'lang'   => 'painless'
                            ],
                            'size'  => 12,
                            'order' => [
                                '_key' => 'asc'
                            ]
                        ],
                        'aggs' => [
                            'by_company' => [
                                'terms' => [
                                    'field' => 'company_name.keyword',
                                    'size'  => 1000,
                                    'order' => [
                                        'total_points' => 'desc'
                                    ]
                                ],
                                'aggs' => [
                                    'total_points' => [
                                        'sum' => [
                                            'field' => 'points'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $result = $client->search($params);
        $dataToDisplay = [];
        $buckets = $result['aggregations']['by_month']['buckets'];

        foreach ($buckets as $bucket) {
            $monthlyData = [];
            foreach ($bucket['by_company']['buckets'] as $companyBucket) {
                $monthlyData[] = [
                    'name' => $companyBucket['key'],
                    'points' => (int)$companyBucket['total_points']['value'],
                ];
            }
            $dataToDisplay[$bucket['key']] = $monthlyData;
        }
        ksort($dataToDisplay);
        return view('leaderboard/monthly', ['data' => $dataToDisplay, 'year' => $year]);
    }
}
