<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataTransferObjects\Misc\InfoText;
use App\Services\Search\OpenSearchService;
use DateTime;
use Illuminate\View\View;
use OpenSearch\Client;

class IssuesByMonthController extends Controller
{
    public function index(Client $client): View
    {
        $dataToDisplay = [];
        $params = [
            'index' => OpenSearchService::getIndexWithPrefix(OpenSearchService::OPENSEARCH_GITHUB_ISSUES_INDEX),
            'body' => [
                'size' => 0,
                'query' => [
                    'term' => [
                        'is_open' => true
                    ]
                ],
                'aggs' => [
                    'by_year' => [
                        'date_histogram' => [
                            'field' => 'updated_at',
                            'calendar_interval' => 'year',
                            'format' => 'yyyy',
                            'order' => ['_key' => 'desc'],
                            'min_doc_count' => 1
                        ],
                        'aggs' => [
                            'by_month' => [
                                'date_histogram' => [
                                    'field' => 'updated_at',
                                    'calendar_interval' => 'month',
                                    'format' => 'MM',
                                    'order' => ['_key' => 'asc'],
                                    'min_doc_count' => 1
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        try {
            $result = $client->search($params);
        } catch (\Exception $e) {
            abort(500, 'Error fetching Issue data: ' . $e->getMessage());
        }
        foreach ($result['aggregations']['by_year']['buckets'] as $yearBucket) {
            $dataToDisplay[$yearBucket['key_as_string']] = [
                'year' => $yearBucket['key_as_string'],
                'total' => $yearBucket['doc_count'],
                'months' => [
                    '01' => ['month_number' => '01', 'total' => 0, 'start' => null, 'end' => null],
                    '02' => ['month_number' => '02', 'total' => 0, 'start' => null, 'end' => null],
                    '03' => ['month_number' => '03', 'total' => 0, 'start' => null, 'end' => null],
                    '04' => ['month_number' => '04', 'total' => 0, 'start' => null, 'end' => null],
                    '05' => ['month_number' => '05', 'total' => 0, 'start' => null, 'end' => null],
                    '06' => ['month_number' => '06', 'total' => 0, 'start' => null, 'end' => null],
                    '07' => ['month_number' => '07', 'total' => 0, 'start' => null, 'end' => null],
                    '08' => ['month_number' => '08', 'total' => 0, 'start' => null, 'end' => null],
                    '09' => ['month_number' => '09', 'total' => 0, 'start' => null, 'end' => null],
                    '10' => ['month_number' => '10', 'total' => 0, 'start' => null, 'end' => null],
                    '11' => ['month_number' => '11', 'total' => 0, 'start' => null, 'end' => null],
                    '12' => ['month_number' => '12', 'total' => 0, 'start' => null, 'end' => null],
                ]
            ];
            foreach ($yearBucket['by_month']['buckets'] as $monthBucket) {
                $dataToDisplay[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['total'] =
                    $monthBucket['doc_count'];
                $monthDate =
                    Datetime::createFromFormat('Y-m-d',
                        $yearBucket['key_as_string'] . '-' . $monthBucket['key_as_string'] . '-01');
                $firstOfMonth = (clone $monthDate)->modify('first day of this month')->setTime(0, 0, 0);
                $lastOfMonth = (clone $monthDate)->modify('last day of this month')->setTime(23, 59, 59);
                $dataToDisplay[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['start'] =
                    $firstOfMonth->format('Y-m-d\TH:i:s\Z');
                $dataToDisplay[$yearBucket['key_as_string']]['months'][$monthBucket['key_as_string']]['end'] =
                    $lastOfMonth->format('Y-m-d\TH:i:s\Z');
            }
        }

        return view('issuesByMonth/index', [
                'infoText' => $this->getInfoText(),
                'issues' => $dataToDisplay
            ]
        );
    }

    private function getInfoText(): InfoText
    {
        return new InfoText(
            title: 'Why Group Open Issues by Month?',
            paragraphs: [
                'A long list of open issues can be daunting and discouraging. To make things more manageable, we group issues by the month they were last updated. This breaks the backlog into smaller, more approachable segments.',
                'Developers can focus on a specific month—like issues from March—and make visible progress. Each update or resolution shortens the list, providing a clear sense of momentum and accomplishment.',
                'This view also makes it easier to spot older issues that may have fallen through the cracks, giving the community an opportunity to reassess and take action where needed.'
            ]
        );
    }
}
