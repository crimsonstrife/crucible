<?php

namespace App\Filament\Resources\Repository\Pages;

use App\Filament\Resources\RepositoryResource;
use App\Jobs\InitializeRepositoryJob;
use Filament\Resources\Pages\CreateRecord;

class CreateRepository extends CreateRecord
{
    protected static string $resource = RepositoryResource::class;

    protected function afterCreate(): void
    {
        InitializeRepositoryJob::dispatch($this->record);
    }
}
