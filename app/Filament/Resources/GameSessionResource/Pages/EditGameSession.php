<?php

namespace App\Filament\Resources\GameSessionResource\Pages;

use App\Filament\Resources\GameSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGameSession extends EditRecord
{
    protected static string $resource = GameSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
