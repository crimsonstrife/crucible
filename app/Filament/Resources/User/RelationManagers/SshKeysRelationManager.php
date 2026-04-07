<?php

namespace App\Filament\Resources\User\RelationManagers;

use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SshKeysRelationManager extends RelationManager
{
    protected static string $relationship = 'sshKeys';

    protected static ?string $title = 'SSH Keys';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('fingerprint')->copyable()->wrap(),
                Tables\Columns\TextColumn::make('key_type')->label('Type'),
                Tables\Columns\IconColumn::make('is_deploy_key')->label('Deploy Key')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Created'),
            ])
            ->recordActions([
                Actions\DeleteAction::make(),
            ]);
    }
}
