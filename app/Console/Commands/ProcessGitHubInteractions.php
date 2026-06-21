<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Search\OpenSearchService;
use Illuminate\Console\Command;
use OpenSearch\Client;

class ProcessGitHubInteractions extends Command
{
    private const INDEX = OpenSearchService::OPENSEARCH_GITHUB_INTERACTIONS_INDEX;

    private const NEW_INDEX = 'points';

    protected $signature = 'opensearch:process-interactions';

    protected $description = 'Assign points to GitHub interactions and store results in a new OpenSearch index.';

    /**
     * @throws \JsonException
     */
    public function handle(Client $client): void
    {
        $scrollTimeout = '1m';
        $pageSize = 500;

        $userMap = User::all()->keyBy('github_username');

        $missingUsers = 0;

        $params = [
            'index' => OpenSearchService::getIndexWithPrefix(self::INDEX),
            'scroll' => $scrollTimeout,
            'size' => $pageSize,
            '_source' => ['github_account_name', 'interaction_date', 'interaction_name', 'issues-id'],
            'body' => [
                'query' => [
                    'match_all' => (object) [],
                ],
            ],
        ];

        $response = $client->search($params);
        $scrollId = $response['_scroll_id'];
        $documents = $response['hits']['hits'];
        $total = $response['hits']['total']['value'] ?? count($documents);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        while (! empty($documents)) {
            foreach ($documents as $doc) {
                $source = $doc['_source'];

                if (empty($source['github_account_name']) || empty($source['interaction_name'])) {
                    $this->warn("Skipping document {$doc['_id']}: missing required fields.");
                    $bar->advance();

                    continue;
                }

                $githubUsername = $source['github_account_name'] ?? null;

                $realName = 'unclaimed by user';

                $user = $userMap->get($githubUsername);

                if ($user) {
                    $realName = $user->name;
                } else {
                    $missingUsers++;
                }

                $source['points'] = $this->assignPoints($source['interaction_name'] ?? '');
                $source['real_name'] = $realName;

                // Generate deterministic ID for upsert behavior (prevents duplicates)
                $docId = sha1(json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                $client->index([
                    'index' => OpenSearchService::getIndexWithPrefix(self::NEW_INDEX),
                    'id' => $docId,
                    'body' => $source,
                ]);

                $bar->advance();
            }

            $scrollParams = [
                'scroll_id' => $scrollId,
                'scroll' => $scrollTimeout,
            ];

            $response = $client->scroll($scrollParams);
            $scrollId = $response['_scroll_id'];
            $documents = $response['hits']['hits'];
        }

        $bar->finish();
        $this->info("\nFinished processing all GitHub interactions.");
        $this->info("Missing users: $missingUsers");
    }

    private function assignPoints(string $interaction): int
    {
        return match ($interaction) {
            'comment', 'connected', 'reopened', 'unlabeled', 'labeled' => 5,
            'mentioned' => 3,
            'subscribed', 'unsubscribed', 'unassigned' => 1,
            'assigned' => 8,
            'closed', 'demilestoned', 'milestoned' => 10,
            'renamed' => 2,
            'referenced' => 4,
            'comment_deleted' => -2,
            default => 0,
        };
    }
}
