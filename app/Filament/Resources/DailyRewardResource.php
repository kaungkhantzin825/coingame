<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DailyRewardResource\Pages;
use App\Filament\Resources\DailyRewardResource\RelationManagers;
use App\Models\DailyReward;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DailyRewardResource extends Resource
{
    protected static ?string $model = DailyReward::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Configuration';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('day')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('reward_coins')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('reward_cash_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('reward_gems')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('is_bonus')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('day')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_coins')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_cash_cents')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_gems')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_bonus')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDailyRewards::route('/'),
            'create' => Pages\CreateDailyReward::route('/create'),
            'edit' => Pages\EditDailyReward::route('/{record}/edit'),
        ];
    }
}
