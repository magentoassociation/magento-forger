<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\User;
use App\Services\Search\OpenSearchService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * GitHub Statistics Widget for Filament Admin Dashboard
 * Displays key statistics about GitHub user interactions, including claimed/unclaimed users,
 * registered companies, and registered users on the Forger platform.
 *
 * @package App\Filament\Widgets
 */
class GitHubStats extends BaseWidget
{
    /**
     * Get the statistics to display in the widget
     * Retrieves and formats statistics including:
     * - Total claimed users (users who have registered on Forger)
     * - Total unclaimed users (GitHub users who haven't registered)
     * - Percentage of claimed users
     * - Total companies registered on Forger
     * - Total users registered on Forger
     * If the points index doesn't exist, displays an error message with instructions.
     *
     * @return array<Stat> Array of Stat objects for display
     */
    protected function getStats(): array
    {
        try {
            $openSearchStats = $this->getTotalUserNamesFromInteractions();

            return [
                Stat::make('Total Users claimed', number_format($openSearchStats['claimed_users']))
                    ->color('success')
                    ->description('Total Users Claimed'),
                Stat::make('Total Users unclaimed', number_format($openSearchStats['unclaimed_users']))
                    ->color('warning')
                    ->description('Total Users Unclaimed'),
                Stat::make('Percent claimed', number_format($openSearchStats['percentClaimed'], 5) . '%'),
                Stat::make('Companies', (new Company)->count())->description('Companies registered on Forger'),
                Stat::make('Users', (new User)->count())->description('Users registered on Forger'),
                Stat::make('Total GitHub Users', number_format($openSearchStats['claimed_users'] + $openSearchStats['unclaimed_users']))
                    ->description('Total unique contributors'),
            ];
        } catch (\Throwable $e) {
            // Check if it's an index not found error
            if (str_contains($e->getMessage(), 'index_not_found_exception') ||
                str_contains($e->getMessage(), 'no such index')
            ) {
                return [
                    Stat::make('Points Index Missing', 'Not Available')
                        ->color('danger')
                        ->description('Run: php artisan opensearch:process-interactions')
                        ->descriptionIcon('heroicon-m-exclamation-triangle'),
                    Stat::make('Total Users unclaimed', '—')
                        ->color('gray')
                        ->description('Data unavailable'),
                    Stat::make('Percent claimed', '—'),
                    Stat::make('Companies', (new Company)->count())->description('Companies registered on Forger'),
                    Stat::make('Users', (new User)->count())->description('Users registered on Forger'),
                    Stat::make('Total GitHub Users', '—')
                        ->description('Data unavailable'),
                ];
            }

            // Re-throw other exceptions
            throw $e;
        }
    }

    /**
     * Retrieve user statistics from OpenSearch interactions index
     * Queries the OpenSearch 'points' index to aggregate statistics about claimed and unclaimed users.
     * Claimed users are those who have registered on Forger and have a real name associated with their
     * GitHub interactions. Unclaimed users have 'unclaimed by user' as their real_name value.
     * Uses OpenSearch aggregations to:
     * - Count unique GitHub accounts with unclaimed status
     * - Count unique real names for claimed users
     * - Calculate the percentage of claimed users
     *
     * @return array{unclaimed_users: int, claimed_users: int, percentClaimed: float} Statistics array containing:
     *               - unclaimed_users: Count of unique unclaimed GitHub accounts
     *               - claimed_users: Count of unique claimed users (by real name)
     *               - percentClaimed: Percentage of claimed users out of total users
     */
    protected function getTotalUserNamesFromInteractions(): array
    {
        $client = new OpenSearchService();
        $params = [
            'size' => 0,
            'aggs' => [
                'unclaimed_users' => [
                    'filter' => [
                        'term' => [
                            'real_name.keyword' => 'unclaimed by user'
                        ]
                    ],
                    'aggs' => [
                        'unique_github_accounts' => [
                            'cardinality' => [
                                'field' => 'github_account_name.keyword'
                            ]
                        ]
                    ]
                ],
                'claimed_users' => [
                    'filter' => [
                        'bool' => [
                            'must_not' => [
                                'term' => [
                                    'real_name.keyword' => 'unclaimed by user'
                                ]
                            ]
                        ]
                    ],
                    'aggs' => [
                        'unique_real_names' => [
                            'cardinality' => [
                                'field' => 'real_name.keyword'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $data = $client->search(OpenSearchService::getIndexWithPrefix('points'), $params);

        $claimed = $data['aggregations']['claimed_users']['unique_real_names']['value'];
        $unclaimed = $data['aggregations']['unclaimed_users']['unique_github_accounts']['value'];
        $total = $claimed + $unclaimed;
        
        return [
            'unclaimed_users' => $unclaimed,
            'claimed_users' => $claimed,
            'percentClaimed' => $total > 0 ? ($claimed / $total) * 100 : 0,
        ];
    }
}
