<?php

namespace App\Filament\Resources\Repository\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FileLockRelationManager extends RelationManager
{
    protected static string $relationship = 'fileLocks';

    protected static ?string $title = 'File Locks';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                Tables\Columns\TextColumn::make('path')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('owner_display_name')
                    ->label('Locked By')
                    ->formatStateUsing(fn (string $state, $record) => $record->owner_external ? "{$state} (External)" : $state),
                Tables\Columns\TextColumn::make('owner_display_identifier')->label('Identifier')->toggleable(),
                Tables\Columns\TextColumn::make('ref')->label('Ref'),
                Tables\Columns\TextColumn::make('locked_at')->dateTime()->label('Locked At'),
            ])
            ->recordActions([
                Actions\DeleteAction::make()->label('Force Unlock'),
            ]);
    }
}
