<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliationsRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliations';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('company_id')
                    ->relationship('company', 'name', fn($query) => $query->where('status', 'approved')->orderBy('name'))
                    ->searchable()
                    ->required()
                    ->label('Company'),
                Forms\Components\DatePicker::make('start_date')
                    ->format('Y-m-d')
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->format('Y-m-d')
                    ->rules(['nullable', 'after_or_equal:start_date']),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('jobs')
            ->columns([
                Tables\Columns\TextColumn::make('company.name')
                    ->label('Company Name'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Start Date')
                    ->date('Y-m-d'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('End Date')
                    ->date('Y-m-d'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
