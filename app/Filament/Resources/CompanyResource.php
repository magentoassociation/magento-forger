<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompanyResource\Pages;
use App\Filament\Resources\CompanyResource\RelationManagers;
use App\Models\Company;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static ?string $navigationIcon = 'heroicon-c-building-storefront';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required()
                    ->default('pending'),

                Forms\Components\TextInput::make('name')->required()->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('linkedin_url')
                    ->label('LinkedIn Company URL')
                    ->url()
                    ->maxLength(500)
                    ->helperText('Validating the LinkedIn URL speeds up approval'),

                Forms\Components\TextInput::make('email')->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('website')->required()->unique(ignoreRecord: true)->url(),
                Forms\Components\TextInput::make('phone')->required(),
                Forms\Components\TextInput::make('address')->required(),
                Forms\Components\TextInput::make('zip')->required(),
                Forms\Components\TextInput::make('city')->required(),
                Forms\Components\TextInput::make('state'),

                Forms\Components\Select::make('country_code')
                    ->label('Country')
                    ->options(
                        collect(countries())
                            ->mapWithKeys(fn($country) => [
                                $country['iso_3166_1_alpha3'] => $country['name'],
                            ])
                            ->sort()
                            ->toArray()
                    )
                    ->searchable(),

                Forms\Components\Toggle::make('is_magento_member')
                    ->label('Is Magento Member'),

                Forms\Components\Toggle::make('is_recommended')
                    ->label('Recommended by Users'),

                Forms\Components\FileUpload::make('logo')
                    ->acceptedFileTypes(['image/png', 'image/jpg', 'image/jpeg', 'image/gif'])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),

                Tables\Columns\IconColumn::make('is_magento_member')
                    ->boolean()
                    ->label('Magento Member'),

                Tables\Columns\IconColumn::make('is_recommended')
                    ->boolean()
                    ->label('User Recommended'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Company $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Company $record): void {
                        $record->status = 'approved';
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Company Approved')
                            ->body("{$record->name} is now visible to all users")
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Company $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Company $record): void {
                        $record->status = 'rejected';
                        $record->save();

                        Notification::make()
                            ->success()
                            ->title('Company Rejected')
                            ->body("{$record->name} has been rejected")
                            ->send();
                    }),

                Tables\Actions\Action::make('merge')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Merge this company into another approved company')
                    ->form([
                        Forms\Components\Select::make('target_company_id')
                            ->label('Merge into Company')
                            ->options(fn(Company $record) =>
                            Company::where('status', 'approved')
                                ->whereNot('id', $record->id)
                                ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Company $record, array $data): void {
                        $targetCompanyId = $data['target_company_id'];

                        // Get user IDs that already have company_affiliations with target company
                        $existingUserIds = DB::table('company_affiliations')
                            ->where('company_id', $targetCompanyId)
                            ->pluck('user_id');

                        // Delete affiliations that would create duplicates
                        $record->affiliations()
                            ->whereIn('user_id', $existingUserIds)
                            ->delete();

                        // Move remaining affiliations to target company
                        $record->affiliations()->update(['company_id' => $targetCompanyId]);

                        $record->update(['status' => 'rejected']);

                        Notification::make()
                            ->success()
                            ->title('Company merged')
                            ->body("{$record->name} merged into " . Company::find($targetCompanyId)->name)
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OwnersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompanies::route('/'),
            'create' => Pages\CreateCompany::route('/create'),
            'edit' => Pages\EditCompany::route('/{record}/edit'),
        ];
    }
}
