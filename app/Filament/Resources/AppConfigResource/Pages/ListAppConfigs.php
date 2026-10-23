<?php

namespace App\Filament\Resources\AppConfigResource\Pages;

use App\Filament\Resources\AppConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppConfigs extends ListRecords
{
    protected static string $resource = AppConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
