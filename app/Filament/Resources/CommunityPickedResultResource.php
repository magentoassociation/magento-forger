<?php
/*
 * @copyright Copyright (c) 2026 The Magento Association
 * @license https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CommunityPickedResultResource\Pages;
use App\Models\CommunityPickedResult;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Monthly Community-Picked results: one screenshot of the top-voted PRs per voting month,
 * shown on the Community-Picked PRs page.
 */
class CommunityPickedResultResource extends Resource
{
    protected static ?string $model = CommunityPickedResult::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Community-Picked results';

    protected static ?string $modelLabel = 'Community-Picked result';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Native month input: the voting month, stored as its first day.
                Forms\Components\TextInput::make('month')
                    ->label('Voting month')
                    ->type('month')
                    ->required()
                    ->regex('/^\d{4}-\d{2}$/')
                    ->formatStateUsing(fn (?string $state): ?string => $state ? substr($state, 0, 7) : null)
                    ->dehydrateStateUsing(fn (string $state): string => $state.'-01')
                    ->rule(fn (?CommunityPickedResult $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $taken = CommunityPickedResult::query()
                            ->whereDate('month', $value.'-01')
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();
                        if ($taken) {
                            $fail('That month already has results.');
                        }
                    }),
                Forms\Components\DatePicker::make('posted_on')
                    ->label('Posted on')
                    ->native()
                    ->default(now())
                    ->required(),
                Forms\Components\FileUpload::make('image_path')
                    ->label('Screenshot')
                    ->image()
                    ->disk('public')
                    ->directory('community-picked')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('enabled')
                    ->helperText('Disabled months are hidden from the Community-Picked PRs page.')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('month', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('month')->date('F Y')->sortable(),
                Tables\Columns\TextColumn::make('posted_on')->date()->sortable(),
                Tables\Columns\ImageColumn::make('image_path')->label('Screenshot')->disk('public'),
                Tables\Columns\ToggleColumn::make('enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunityPickedResults::route('/'),
            'create' => Pages\CreateCommunityPickedResult::route('/create'),
            'edit' => Pages\EditCommunityPickedResult::route('/{record}/edit'),
        ];
    }
}
