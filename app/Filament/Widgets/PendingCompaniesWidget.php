<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CompanyResource;
use App\Models\Company;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingCompaniesWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pending Company Approvals')
            ->description('Review and approve user-submitted companies')
            ->query(
                Company::query()->where('status', 'pending')->orderBy('created_at', 'desc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('linkedin_url')
                    ->label('LinkedIn')
                    ->formatStateUsing(fn($state) => $state ? '✓ Provided' : '—')
                    ->color(fn($state) => $state ? 'success' : 'gray')
                    ->url(fn($record) => $record->linkedin_url ?: null, shouldOpenInNewTab: true),

                Tables\Columns\IconColumn::make('is_recommended')
                    ->label('User Proposed')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Company $record): void {
                        $record->update(['status' => 'approved']);

                        Notification::make()
                            ->success()
                            ->title('Company Approved')
                            ->body("{$record->name} is now visible to all users")
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Company $record): void {
                        $record->update(['status' => 'rejected']);

                        Notification::make()
                            ->success()
                            ->title('Company Rejected')
                            ->body("{$record->name} has been rejected")
                            ->send();
                    }),

                Tables\Actions\Action::make('view')
                    ->label('View Full Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Company $record): string => CompanyResource::getUrl('edit', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No Pending Approvals')
            ->emptyStateDescription('All companies have been reviewed')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->paginated([10, 25, 50]);
    }
}
