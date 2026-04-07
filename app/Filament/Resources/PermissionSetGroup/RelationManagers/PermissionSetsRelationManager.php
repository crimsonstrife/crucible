<?php

namespace App\Filament\Resources\PermissionSetGroup\RelationManagers;

use App\Models\PermissionSet;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PermissionSetsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissionSets';

    protected static ?string $title = 'Permission Sets';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('description')->limit(60),
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->label('Attach Set')
                    ->recordSelectOptionsQuery(fn () => PermissionSet::query()->orderBy('name'))
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                Actions\DetachAction::make()->label('Remove'),
            ]);
    }
}
