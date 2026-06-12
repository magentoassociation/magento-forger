<?php

/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Services\GitHub;

use App\Models\User;
use App\Services\Search\OpenSearchService;
use Carbon\Carbon;
use OpenSearch\Client;

class InteractionPointsProcessor
{
    public function __construct(private readonly Client $client) {}

    /**
     * Scroll through synced interactions, assign points, and write to the points index.
     *
     * @param  callable(int $total): void|null  $onStart  Called once when total document count is known.
     * @param  callable(): void|null  $onAdvance  Called after each document is processed.
     * @return array{processed: int, missingUsers: int, missingAffiliations: int}
     *
     * @throws \JsonException
     */
    public function process(
        ?callable $onStart = null,
        ?callable $onAdvance = null,
    ): array {
        $scrollTimeout = '1m';
        $pageSize = 500;

        $users = User::with(['affiliations.company'])->get();
        $userMap = $users->keyBy('github_username');

        $missingUsers = 0;
        $missingAffiliations = 0;
        $processed = 0;

        $response = $this->client->search([
            'index' => OpenSearchService::getIndexWithPrefix('interactions'),
            'scroll' => $scrollTimeout,
            'size' => $pageSize,
            '_source' => ['github_account_name', 'interaction_date', 'interaction_name', 'issues-id'],
            'body' => ['query' => ['match_all' => (object) []]],
        ]);

        $scrollId = $response['_scroll_id'];
        $documents = $response['hits']['hits'];
        $total = $response['hits']['total']['value'] ?? count($documents);

        if ($onStart !== null) {
            $onStart($total);
        }

        while (! empty($documents)) {
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
                        return $aff->start_date <= $date
                            && ($aff->end_date === null || $aff->end_date >= $date);
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

                if (str_starts_with((string) $source['github_account_name'], 'engcom-')) {
                    $realName = 'Adobe';
                    $companyName = 'Adobe';
                }

                $source['points'] = $this->assignPoints($source['interaction_name'] ?? '');
                $source['real_name'] = $realName;
                $source['company_name'] = $companyName;

                $docId = sha1(json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $this->client->index([
                    'index' => OpenSearchService::getIndexWithPrefix('points'),
                    'id' => $docId,
                    'body' => $source,
                ]);

                $processed++;

                if ($onAdvance !== null) {
                    $onAdvance();
                }
            }

            $response = $this->client->scroll(['scroll_id' => $scrollId, 'scroll' => $scrollTimeout]);
            $scrollId = $response['_scroll_id'];
            $documents = $response['hits']['hits'];
        }

        return [
            'processed' => $processed,
            'missingUsers' => $missingUsers,
            'missingAffiliations' => $missingAffiliations,
        ];
    }

    private function assignPoints(string $interaction): int
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
            default => 0,
        };
    }
}
