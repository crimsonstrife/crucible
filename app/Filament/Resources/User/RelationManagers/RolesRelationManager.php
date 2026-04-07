<?php

namespace App\Filament\Resources\User\RelationManagers;

use App\Models\Role;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static ?string $title = 'Roles';

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
                    ->label('Grant Role')
                    ->recordSelectOptionsQuery(fn () => Role::query()->orderBy('name'))
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                Actions\DetachAction::make()->label('Revoke'),
            ]);
    }
}
