<?php

namespace App\Filament\Resources\AppConfigResource\Pages;

use App\Filament\Resources\AppConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppConfig extends EditRecord
{
    protected static string $resource = AppConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
