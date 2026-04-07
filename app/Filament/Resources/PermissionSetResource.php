<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionSet\Pages\CreatePermissionSet;
use App\Filament\Resources\PermissionSet\Pages\EditPermissionSet;
use App\Filament\Resources\PermissionSet\Pages\ListPermissionSets;
use App\Filament\Resources\PermissionSet\RelationManagers\MutedPermissionsRelationManager;
use App\Filament\Resources\PermissionSet\RelationManagers\PermissionsRelationManager;
use App\Models\PermissionSet;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermissionSetResource extends Resource
{
    protected static ?string $model = PermissionSet::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            Textarea::make('description')->nullable()->rows(3),
            Toggle::make('is_system')->label('System Permission Set')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('description')->limit(60),
                IconColumn::make('is_system')->label('System')->boolean(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissionSets::route('/'),
            'create' => CreatePermissionSet::route('/create'),
            'edit' => EditPermissionSet::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            PermissionsRelationManager::class,
            MutedPermissionsRelationManager::class,
        ];
    }
}
