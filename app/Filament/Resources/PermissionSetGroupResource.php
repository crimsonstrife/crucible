<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PermissionSetGroup\Pages\CreatePermissionSetGroup;
use App\Filament\Resources\PermissionSetGroup\Pages\EditPermissionSetGroup;
use App\Filament\Resources\PermissionSetGroup\Pages\ListPermissionSetGroups;
use App\Filament\Resources\PermissionSetGroup\RelationManagers\PermissionSetsRelationManager;
use App\Models\PermissionSetGroup;
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

class PermissionSetGroupResource extends Resource
{
    protected static ?string $model = PermissionSetGroup::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            Textarea::make('description')->nullable()->rows(3),
            Toggle::make('is_system')->label('System Group')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('description')->limit(60),
                IconColumn::make('is_system')->label('System')->boolean(),
                TextColumn::make('permission_sets_count')->counts('permissionSets')->label('Permission Sets'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            PermissionSetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissionSetGroups::route('/'),
            'create' => CreatePermissionSetGroup::route('/create'),
            'edit' => EditPermissionSetGroup::route('/{record}/edit'),
        ];
    }
}
