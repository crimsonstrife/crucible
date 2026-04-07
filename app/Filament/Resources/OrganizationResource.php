<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Organization\Pages\CreateOrganization;
use App\Filament\Resources\Organization\Pages\EditOrganization;
use App\Filament\Resources\Organization\Pages\ListOrganizations;
use App\Filament\Resources\Organization\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Organization\RelationManagers\RepositoriesRelationManager;
use App\Models\Organization;
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

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Access';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('slug')->required()->maxLength(100),
            Textarea::make('description')->nullable()->rows(3),
            TextInput::make('website_url')->url()->nullable(),
            Toggle::make('is_personal')->label('Personal Organization'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable(),
                IconColumn::make('is_personal')->label('Personal')->boolean(),
                TextColumn::make('members_count')->counts('members')->label('Members'),
                TextColumn::make('repositories_count')->counts('repositories')->label('Repos'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'create' => CreateOrganization::route('/create'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            MembersRelationManager::class,
            RepositoriesRelationManager::class,
        ];
    }
}
