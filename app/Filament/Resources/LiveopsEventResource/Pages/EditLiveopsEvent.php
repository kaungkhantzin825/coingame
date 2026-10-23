<?php

namespace App\Filament\Resources\LiveopsEventResource\Pages;

use App\Filament\Resources\LiveopsEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLiveopsEvent extends EditRecord
{
    protected static string $resource = LiveopsEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
