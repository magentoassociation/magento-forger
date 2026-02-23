<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CompanyResource;
use App\Models\Company;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingCompaniesStats extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $statusCounts = Company::selectRaw('status, COUNT(*) as count')
            ->whereIn('status', ['pending', 'approved', 'rejected'])
            ->groupBy('status')
            ->pluck('count', 'status');

        $pendingCount = $statusCounts['pending'] ?? 0;
        $approvedCount = $statusCounts['approved'] ?? 0;
        $rejectedCount = $statusCounts['rejected'] ?? 0;

        return [
            Stat::make('Pending Approvals', $pendingCount)
                ->description('Companies awaiting review')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingCount > 0 ? 'warning' : 'success')
                ->url(CompanyResource::getUrl('index', [
                    'tableFilters' => ['status' => ['value' => 'pending']]
                ])),

            Stat::make('Approved Companies', $approvedCount)
                ->description('Active companies')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Rejected Companies', $rejectedCount)
                ->description('Declined submissions')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
