<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawMethodResource\Pages;
use App\Filament\Resources\WithdrawMethodResource\RelationManagers;
use App\Models\WithdrawMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WithdrawMethodResource extends Resource
{
    protected static ?string $model = WithdrawMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Configuration';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('type')
                    ->required()
                    ->maxLength(20)
                    ->default('ewallet'),
                Forms\Components\TextInput::make('min_cents')
                    ->required()
                    ->numeric()
                    ->default(500),
                Forms\Components\TextInput::make('fee_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('fee_percent')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('fields')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('active')
                    ->required(),
                Forms\Components\TextInput::make('sort')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('min_cents')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fee_cents')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fee_percent')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort')
                    ->numeric()
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
            'index' => Pages\ListWithdrawMethods::route('/'),
            'create' => Pages\CreateWithdrawMethod::route('/create'),
            'edit' => Pages\EditWithdrawMethod::route('/{record}/edit'),
        ];
    }
}
