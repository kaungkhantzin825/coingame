<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdViewResource\Pages;
use App\Filament\Resources\AdViewResource\RelationManagers;
use App\Models\AdView;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AdViewResource extends Resource
{
    protected static ?string $model = AdView::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Ads';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('placement')
                    ->required()
                    ->maxLength(40),
                Forms\Components\TextInput::make('network')
                    ->maxLength(30)
                    ->default(null),
                Forms\Components\TextInput::make('idempotency_key')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('reward_coins')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('ecpm_cents')
                    ->numeric()
                    ->default(null),
                Forms\Components\DateTimePicker::make('provisional_at'),
                Forms\Components\Toggle::make('verified')
                    ->required(),
                Forms\Components\DateTimePicker::make('verified_at'),
                Forms\Components\Toggle::make('revoked')
                    ->required(),
                Forms\Components\Textarea::make('ssv_payload')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('ip')
                    ->maxLength(45)
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('placement')
                    ->searchable(),
                Tables\Columns\TextColumn::make('network')
                    ->searchable(),
                Tables\Columns\TextColumn::make('idempotency_key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('reward_coins')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ecpm_cents')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provisional_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('verified')
                    ->boolean(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('revoked')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ip')
                    ->searchable(),
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
            'index' => Pages\ListAdViews::route('/'),
            'create' => Pages\CreateAdView::route('/create'),
            'edit' => Pages\EditAdView::route('/{record}/edit'),
        ];
    }
}
