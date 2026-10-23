<?php

namespace App\Filament\Pages;

use App\Models\AppConfig;
use App\Support\Cfg;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * The referral program, priced by the admin.
 *
 * Writes straight to `app_configs`, which is what `Cfg` reads, so a save here
 * changes the payout immediately — no deploy, no cache flush.
 */
class ReferralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-gift-top';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Referral Program';

    protected static ?string $title = 'Referral Program';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.referral-settings';

    /** @var array<string, string> */
    public const DESCRIPTIONS = [
        'referral_enabled' => 'Show the invite screen and accept codes.',
        'referral_bonus_coins' => 'Coins paid to the player who invited, once the friend qualifies.',
        'referral_referee_bonus_coins' => 'Coins paid to the new player the moment they enter a code.',
        'referral_qualify_sessions' => 'Finished runs the friend must play before the inviter is paid.',
    ];

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'referral_enabled' => Cfg::bool('referral_enabled'),
            'referral_bonus_coins' => Cfg::int('referral_bonus_coins'),
            'referral_referee_bonus_coins' => Cfg::int('referral_referee_bonus_coins'),
            'referral_qualify_sessions' => Cfg::int('referral_qualify_sessions'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Program')
                    ->schema([
                        Forms\Components\Toggle::make('referral_enabled')
                            ->label('Invite program enabled')
                            ->helperText(self::DESCRIPTIONS['referral_enabled'])
                            ->inline(false),
                    ]),

                Forms\Components\Section::make('Rewards')
                    ->description('Amounts are in coins. The client only displays these numbers — the server always applies them.')
                    ->schema([
                        Forms\Components\TextInput::make('referral_bonus_coins')
                            ->label('Inviter reward (coins)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10000000)
                            ->required()
                            ->helperText(self::DESCRIPTIONS['referral_bonus_coins']),

                        Forms\Components\TextInput::make('referral_referee_bonus_coins')
                            ->label('New player reward (coins)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10000000)
                            ->required()
                            ->helperText(self::DESCRIPTIONS['referral_referee_bonus_coins']),

                        Forms\Components\TextInput::make('referral_qualify_sessions')
                            ->label('Runs before the inviter is paid')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->helperText(self::DESCRIPTIONS['referral_qualify_sessions']),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            AppConfig::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => 'referral',
                    'description' => self::DESCRIPTIONS[$key] ?? null,
                ],
            );
        }

        Notification::make()
            ->title('Referral settings saved')
            ->body('The new amounts apply to the next code redeemed.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save changes')
                ->submit('save'),
        ];
    }
}
