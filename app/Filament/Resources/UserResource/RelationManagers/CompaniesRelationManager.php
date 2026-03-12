<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\CompanyResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class CompaniesRelationManager extends RelationManager
{
    protected static string $relationship = 'companies';

    protected static ?string $title = 'Owned Companies';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Company Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn($record) => CompanyResource::getUrl('edit', ['record' => $record]))
                    ->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('website')
                    ->url(function ($record) {
                        $url = $record->website ?? null;

                        if (! is_string($url) || $url === '') {
                            return null;
                        }

                        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
                        if ($validatedUrl === false) {
                            return null;
                        }

                        $scheme = parse_url($validatedUrl, PHP_URL_SCHEME);
                        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                            return null;
                        }

                        return $validatedUrl;
                    })
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('city'),
                Tables\Columns\IconColumn::make('is_magento_member')
                    ->boolean()
                    ->label('Magento Member'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'website', 'email'])
                    ->label('Add Company Ownership'),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Remove Ownership'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
