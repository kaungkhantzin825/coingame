<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdPlacementResource\Pages;
use App\Filament\Resources\AdPlacementResource\RelationManagers;
use App\Models\AdPlacement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AdPlacementResource extends Resource
{
    protected static ?string $model = AdPlacement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Ads';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('placement')
                    ->required()
                    ->maxLength(40),
                Forms\Components\TextInput::make('network')
                    ->required()
                    ->maxLength(30),
                Forms\Components\TextInput::make('ad_unit_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('format')
                    ->required()
                    ->maxLength(20)
                    ->default('rewarded'),
                Forms\Components\TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(100),
                Forms\Components\TextInput::make('floor_ecpm_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('reward_coins')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('daily_cap')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Toggle::make('active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('placement')
                    ->searchable(),
                Tables\Columns\TextColumn::make('network')
                    ->searchable(),
                Tables\Columns\TextColumn::make('ad_unit_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('format')
                    ->searchable(),
                Tables\Columns\TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('floor_ecpm_cents')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_coins')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('daily_cap')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
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
            'index' => Pages\ListAdPlacements::route('/'),
            'create' => Pages\CreateAdPlacement::route('/create'),
            'edit' => Pages\EditAdPlacement::route('/{record}/edit'),
        ];
    }
}
