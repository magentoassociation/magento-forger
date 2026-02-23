<?php
declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\AffiliationsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\CompaniesRelationManager;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-m-user-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->description('User data is synced from GitHub and cannot be edited manually.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('email')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('github_username')
                            ->label('GitHub Username')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('github_id')
                            ->label('GitHub ID')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('github_username')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            AffiliationsRelationManager::class,
            CompaniesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            // Users are created via GitHub OAuth only, not through admin panel
            // 'create' => Pages\CreateUser::route('/create'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Prevent user creation through admin panel
    }
}
