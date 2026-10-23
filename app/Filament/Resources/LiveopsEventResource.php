<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiveopsEventResource\Pages;
use App\Filament\Resources\LiveopsEventResource\RelationManagers;
use App\Models\LiveopsEvent;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LiveopsEventResource extends Resource
{
    protected static ?string $model = LiveopsEvent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Configuration';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('kind')
                    ->required()
                    ->maxLength(20)
                    ->default('tap_timer'),
                Forms\Components\TextInput::make('duration_seconds')
                    ->required()
                    ->numeric()
                    ->default(60),
                Forms\Components\TextInput::make('reward_coins')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('reward_cash_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('ad_multiplier')
                    ->required()
                    ->numeric()
                    ->default(2),
                Forms\Components\TextInput::make('cooldown_seconds')
                    ->required()
                    ->numeric()
                    ->default(3600),
                Forms\Components\Toggle::make('active')
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('ends_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kind')
                    ->searchable(),
                Tables\Columns\TextColumn::make('duration_seconds')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_coins')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reward_cash_cents')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ad_multiplier')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cooldown_seconds')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
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
            'index' => Pages\ListLiveopsEvents::route('/'),
            'create' => Pages\CreateLiveopsEvent::route('/create'),
            'edit' => Pages\EditLiveopsEvent::route('/{record}/edit'),
        ];
    }
}
