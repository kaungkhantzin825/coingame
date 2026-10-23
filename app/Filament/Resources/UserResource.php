<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\LedgerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Players';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name'),
            Forms\Components\TextInput::make('email'),
            Forms\Components\TextInput::make('referral_code')->disabled(),
            Forms\Components\Select::make('status')->options([
                'active' => 'Active', 'flagged' => 'Flagged', 'banned' => 'Banned',
            ]),
            Forms\Components\Toggle::make('is_admin'),
            Forms\Components\TextInput::make('coins')->numeric()->disabled()
                ->helperText('Use the "Adjust balance" action — never edit directly.'),
            Forms\Components\TextInput::make('cash_cents')->numeric()->disabled(),
            Forms\Components\TextInput::make('gems')->numeric()->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()
                    ->description(fn (User $r) => $r->email),
                Tables\Columns\TextColumn::make('coins')->numeric()->sortable(),
                Tables\Columns\TextColumn::make('cash_cents')->label('Cash')
                    ->money('MYR', divideBy: 100)->sortable(),
                Tables\Columns\TextColumn::make('gems')->numeric()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('daily_streak')->toggleable(),
                Tables\Columns\TextColumn::make('total_withdrawn_cents')->label('Withdrawn')
                    ->money('MYR', divideBy: 100)->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success', 'flagged' => 'warning', 'banned' => 'danger', default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_admin')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'active' => 'Active', 'flagged' => 'Flagged', 'banned' => 'Banned',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('adjust')
                    ->label('Adjust balance')->icon('heroicon-m-plus-circle')->color('info')
                    ->form([
                        Forms\Components\TextInput::make('coins')->numeric()->default(0)
                            ->helperText('Positive to credit, negative to debit.'),
                        Forms\Components\TextInput::make('cash_cents')->numeric()->default(0)->label('Cash (cents)'),
                        Forms\Components\TextInput::make('gems')->numeric()->default(0),
                        Forms\Components\TextInput::make('reason')->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        try {
                            app(LedgerService::class)->apply(
                                $record, 'admin_adjust',
                                ['coins' => (int) $data['coins'], 'cash_cents' => (int) $data['cash_cents'], 'gems' => (int) $data['gems']],
                                source: 'admin',
                                meta: ['reason' => $data['reason'], 'by' => auth()->id()],
                            );
                            Notification::make()->title('Balance adjusted')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                Tables\Actions\Action::make('toggleBan')
                    ->label(fn (User $r) => $r->status === 'banned' ? 'Unban' : 'Ban')
                    ->icon('heroicon-m-no-symbol')
                    ->color(fn (User $r) => $r->status === 'banned' ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update([
                        'status' => $record->status === 'banned' ? 'active' : 'banned',
                        'banned_at' => $record->status === 'banned' ? null : now(),
                    ])),

                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
