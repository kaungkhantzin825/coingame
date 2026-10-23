<?php

namespace App\Filament\Resources\AdViewResource\Pages;

use App\Filament\Resources\AdViewResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAdView extends EditRecord
{
    protected static string $resource = AdViewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
