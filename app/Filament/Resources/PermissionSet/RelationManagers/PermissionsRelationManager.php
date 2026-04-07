<?php

namespace App\Filament\Resources\PermissionSet\RelationManagers;

use App\Models\Permission;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    protected static ?string $title = 'Permissions';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->badge()->searchable(),
                Tables\Columns\TextColumn::make('guard_name')->label('Guard'),
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->label('Attach Permission')
                    ->recordSelectOptionsQuery(fn () => Permission::query()->orderBy('name'))
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                Actions\DetachAction::make()->label('Remove'),
            ]);
    }
}
