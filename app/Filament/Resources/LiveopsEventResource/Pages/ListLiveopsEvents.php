<?php

namespace App\Filament\Resources\LiveopsEventResource\Pages;

use App\Filament\Resources\LiveopsEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLiveopsEvents extends ListRecords
{
    protected static string $resource = LiveopsEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
