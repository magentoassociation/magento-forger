<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OwnersRelationManager extends RelationManager
{
    protected static string $relationship = 'owners';

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
            ->recordTitleAttribute('github_username')
            ->columns([
                Tables\Columns\TextColumn::make('github_username')
                    ->label('GitHub Username')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn($record) => route('filament.admin.resources.users.edit', ['record' => $record->id]))
                    ->color('primary'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Real Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['github_username', 'name', 'email'])
                    ->label('Add Owner'),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Remove'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
