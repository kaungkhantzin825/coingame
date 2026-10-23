<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppConfigResource\Pages;
use App\Models\AppConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AppConfigResource extends Resource
{
    protected static ?string $model = AppConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?string $navigationLabel = 'Remote Config';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->disabledOn('edit'),
            Forms\Components\Textarea::make('value')
                ->required()
                ->rows(2)
                ->helperText('JSON: a number (1000), boolean (true/false) or "quoted text".')
                ->afterStateHydrated(function (Forms\Components\Textarea $component, $state) {
                    $component->state(is_string($state) ? $state : json_encode($state));
                })
                ->dehydrateStateUsing(function ($state) {
                    $decoded = json_decode((string) $state, true);

                    return json_last_error() === JSON_ERROR_NONE ? $decoded : $state;
                }),
            Forms\Components\TextInput::make('group')->default('general'),
            Forms\Components\TextInput::make('description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->formatStateUsing(fn ($state) => is_scalar($state) ? (string) var_export($state, true) : json_encode($state))
                    ->limit(60),
                Tables\Columns\TextColumn::make('group')->badge()->sortable(),
                Tables\Columns\TextColumn::make('description')->limit(50)->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('group')
            ->actions([Tables\Actions\EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppConfigs::route('/'),
            'create' => Pages\CreateAppConfig::route('/create'),
            'edit' => Pages\EditAppConfig::route('/{record}/edit'),
        ];
    }
}
