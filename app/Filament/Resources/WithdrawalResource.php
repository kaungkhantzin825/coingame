<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WithdrawalResource\Pages;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WithdrawalResource extends Resource
{
    protected static ?string $model = Withdrawal::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Payouts';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', 'pending')->count();

        return $pending ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false; // withdrawals are created by players via the API
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('info')
                ->content('Withdrawals are created by players. Use the row actions to approve, pay or reject.'),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('reference')->label('Payment reference'),
            Forms\Components\Textarea::make('admin_note'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Player')
                    ->description(fn ($record) => "id {$record->user_id}")->searchable(),
                Tables\Columns\TextColumn::make('method_key')->badge(),
                Tables\Columns\TextColumn::make('amount_cents')->label('Amount')
                    ->money('USD', divideBy: 100)->sortable(),
                Tables\Columns\TextColumn::make('net_cents')->label('Net')
                    ->money('USD', divideBy: 100)->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved', 'processing' => 'info',
                        'paid' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })->sortable(),
                Tables\Columns\TextColumn::make('account')
                    ->formatStateUsing(fn ($state) => collect($state)->map(fn ($v, $k) => "$k: $v")->implode('  ·  '))
                    ->wrap()->toggleable(),
                Tables\Columns\TextColumn::make('reference')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('processed_at')->dateTime()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending' => 'Pending', 'approved' => 'Approved',
                    'processing' => 'Processing', 'paid' => 'Paid', 'rejected' => 'Rejected',
                ]),
                Tables\Filters\SelectFilter::make('method_key')->label('Method')
                    ->options(fn () => \App\Models\WithdrawMethod::pluck('label', 'key')),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-m-check')->color('info')
                    ->visible(fn (Withdrawal $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn (Withdrawal $r) => static::run(fn ($s) => $s->approve($r, auth()->id()))),

                Tables\Actions\Action::make('markPaid')
                    ->label('Mark paid')->icon('heroicon-m-banknotes')->color('success')
                    ->visible(fn (Withdrawal $r) => in_array($r->status, ['pending', 'approved', 'processing']))
                    ->form([Forms\Components\TextInput::make('reference')->label('Payment reference')->required()])
                    ->action(fn (Withdrawal $r, array $data) => static::run(fn ($s) => $s->markPaid($r, auth()->id(), $data['reference']))),

                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-m-x-mark')->color('danger')
                    ->visible(fn (Withdrawal $r) => in_array($r->status, ['pending', 'approved', 'processing']))
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (Withdrawal $r, array $data) => static::run(fn ($s) => $s->reject($r, auth()->id(), $data['reason']))),

                Tables\Actions\ViewAction::make(),
            ]);
    }

    /** Run a WithdrawalService call and surface the result as a notification. */
    protected static function run(\Closure $fn): void
    {
        try {
            $fn(app(WithdrawalService::class));
            Notification::make()->title('Done')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawals::route('/'),
            'view' => Pages\ViewWithdrawal::route('/{record}'),
        ];
    }
}
