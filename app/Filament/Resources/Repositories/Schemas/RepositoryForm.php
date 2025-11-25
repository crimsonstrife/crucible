<?php

namespace App\Filament\Resources\Repositories\Schemas;

use App\Enums\RepositoryVisibility;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class RepositoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('organization_id')
                    ->relationship('organization', 'name')
                    ->required(),
                Select::make('visibility')
                    ->options(RepositoryVisibility::class)
                    ->default('private')
                    ->required(),
                TextInput::make('default_branch')
                    ->required()
                    ->default('main'),
            ]);
    }
}
