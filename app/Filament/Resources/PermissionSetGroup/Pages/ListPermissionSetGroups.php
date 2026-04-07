<?php

namespace App\Filament\Resources\PermissionSetGroup\Pages;

use App\Filament\Resources\PermissionSetGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPermissionSetGroups extends ListRecords
{
    protected static string $resource = PermissionSetGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
