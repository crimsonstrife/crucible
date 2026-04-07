<?php

namespace App\Filament\Resources\Repository\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LfsObjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'lfsObjects';

    protected static ?string $title = 'LFS Objects';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('oid')
            ->columns([
                Tables\Columns\TextColumn::make('oid')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('size')->numeric()->label('Size'),
                Tables\Columns\TextColumn::make('mime_type')->label('MIME Type'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Created'),
            ])
            ->recordActions([
                Actions\DeleteAction::make(),
            ]);
    }
}
