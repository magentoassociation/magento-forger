<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Services\Search\OpenSearchService;
use OpenSearch\Client;

class AgeOverTimeQuery
{
    public function __construct(private readonly Client $client) {}

    /**
     * @return array<string, int> month string (yyyy-MM) => average days open
     */
    public function execute(string $index): array
    {
        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix($index),
            'body' => [
                'size' => 0,
                'query' => [
                    'term' => ['is_open' => false],
                ],
                'aggs' => [
                    'monthly_closures' => [
                        'date_histogram' => [
                            'field' => 'closed_at',
                            'calendar_interval' => 'month',
                            'format' => 'yyyy-MM',
                        ],
                        'aggs' => [
                            'avg_days_open' => [
                                'avg' => [
                                    'script' => [
                                        'lang' => 'painless',
                                        'source' => <<<'EOT'
if (doc.containsKey('created_at') && doc.containsKey('closed_at') &&
    !doc['created_at'].empty && !doc['closed_at'].empty) {
  return ChronoUnit.DAYS.between(
    doc['created_at'].value.toInstant(),
    doc['closed_at'].value.toInstant()
  );
}
return null;
EOT
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = [];
        foreach ($response['aggregations']['monthly_closures']['buckets'] as $bucket) {
            $result[$bucket['key_as_string']] = (int) $bucket['avg_days_open']['value'];
        }

        return $result;
    }
}
