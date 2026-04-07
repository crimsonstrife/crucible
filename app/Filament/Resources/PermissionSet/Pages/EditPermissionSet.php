<?php

namespace App\Filament\Resources\PermissionSet\Pages;

use App\Filament\Resources\PermissionSetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPermissionSet extends EditRecord
{
    protected static string $resource = PermissionSetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
