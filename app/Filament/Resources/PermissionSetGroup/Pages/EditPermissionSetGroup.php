<?php

namespace App\Filament\Resources\PermissionSetGroup\Pages;

use App\Filament\Resources\PermissionSetGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPermissionSetGroup extends EditRecord
{
    protected static string $resource = PermissionSetGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
