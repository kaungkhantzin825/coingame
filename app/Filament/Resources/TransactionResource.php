<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Players';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Ledger';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user_id')->label('Player')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('type')->badge()->searchable(),
                Tables\Columns\TextColumn::make('coins_delta')->label('Δ coins')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => ($state >= 0 ? '+' : '').number_format($state)),
                Tables\Columns\TextColumn::make('cash_cents_delta')->label('Δ cash')
                    ->money('MYR', divideBy: 100)
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('balance_coins_after')->label('Coins after')->numeric()->toggleable(),
                Tables\Columns\TextColumn::make('balance_cash_after')->label('Cash after')->money('MYR', divideBy: 100)->toggleable(),
                Tables\Columns\TextColumn::make('source')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(
                    fn () => Transaction::query()->distinct()->pluck('type', 'type')
                ),
                Tables\Filters\SelectFilter::make('source')->options([
                    'game' => 'game', 'ad' => 'ad', 'admin' => 'admin', 'system' => 'system',
                ]),
            ])
            ->actions([Tables\Actions\ViewAction::make()])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
