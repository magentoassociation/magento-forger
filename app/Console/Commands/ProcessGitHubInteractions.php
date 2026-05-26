<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Search\OpenSearchService;
use Illuminate\Console\Command;
use OpenSearch\Client;
use App\Models\User;
use Carbon\Carbon;

class ProcessGitHubInteractions extends Command
{
    protected $signature = 'opensearch:process-interactions';

    protected $description = 'Assign points to GitHub interactions and store results in a new OpenSearch index.';

    /**
     * @var Client
     */
    protected Client $client;

    protected string $index = 'interactions';

    protected string $newIndex = 'points';

    public function __construct()
    {
        parent::__construct();
        $this->client = app(Client::class);
    }

    /**
     * @throws \JsonException
     */
    public function handle(): void
    {
        // todo: move logic into a separate class
        $scrollTimeout = '1m';
        $pageSize = 500;

        // Load all users with affiliations and companies once
        $users = User::with(['affiliations.company'])->get();
        $userMap = $users->keyBy('github_username');

        $missingUsers = 0;
        $missingAffiliations = 0;

        // Initial scroll request
        $params = [
            'index' => OpenSearchService::getIndexWithPrefix($this->index),
            'scroll' => $scrollTimeout,
            'size' => $pageSize,
            '_source' => ['github_account_name', 'interaction_date', 'interaction_name', 'issues-id'],
            'body' => [
                'query' => [
                    'match_all' => (object)[]
                ]
            ]
        ];

        $response = $this->client->search($params);
        $scrollId = $response['_scroll_id'];
        $documents = $response['hits']['hits'];
        $total = $response['hits']['total']['value'] ?? count($documents);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        while (!empty($documents)) {
            foreach ($documents as $doc) {
                $source = $doc['_source'];
                $githubUsername = $source['github_account_name'] ?? null;

                $realName = 'unclaimed by user';
                $companyName = 'unclaimed by company';

                $user = $userMap->get($githubUsername);

                if ($user) {
                    $realName = $user->name;
                    $date = Carbon::parse($source['interaction_date']);

                    $affiliation = $user->affiliations->first(function ($aff) use ($date) {
                        return $aff->start_date <= $date &&
                            ($aff->end_date === null || $aff->end_date >= $date);
                    });

                    if ($affiliation && $affiliation->company) {
                        $companyName = $affiliation->company->name;
                    } else {
                        $companyName = 'not working for a company at this time';
                        $missingAffiliations++;
                    }
                } else {
                    $missingUsers++;
                }
                if (str_starts_with($source['github_account_name'], 'engcom-')) {
                    $realName = 'Adobe';
                    $companyName = 'Adobe';
                }

                $source['points'] = $this->assignPoints($source['interaction_name'] ?? '');
                $source['real_name'] = $realName;
                $source['company_name'] = $companyName;

                // Generate deterministic ID for upsert behavior (prevents duplicates)
                $docId = sha1(json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

                $this->client->index([
                    'index' => OpenSearchService::getIndexWithPrefix($this->newIndex),
                    'id' => $docId,
                    'body' => $source
                ]);

                $bar->advance();
            }

            $scrollParams = [
                'scroll_id' => $scrollId,
                'scroll' => $scrollTimeout
            ];

            $response = $this->client->scroll($scrollParams);
            $scrollId = $response['_scroll_id'];
            $documents = $response['hits']['hits'];
        }

        $bar->finish();
        $this->info("\nFinished processing all GitHub interactions.");
        $this->info("Missing users: $missingUsers");
        $this->info("Missing company affiliations: $missingAffiliations");
    }

    private function assignPoints($interaction): int
    {
        return match ($interaction) {
            'commented' => 5,
            'mentioned' => 3,
            'subscribed' => 1,
            'labeled' => 5,
            'unlabeled' => 5,
            'assigned' => 8,
            'unassigned' => 1,
            'closed' => 10,
            'renamed' => 2,
            'referenced' => 4,
            'unsubscribed' => 1,
            'reopened' => 5,
            'milestoned' => 10,
            'comment_deleted' => -2,
            'transferred' => 0,
            'connected' => 5,
            'demilestoned' => 10,
            'parent_issue_added' => 0,
            'pinned' => 0,
            'unpinned' => 0,
            'sub_issue_added' => 0,
            default => 0
        };
    }
}
