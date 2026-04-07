<?php

namespace App\Filament\Resources;

use App\Enums\RepositoryVisibility;
use App\Enums\VcsType;
use App\Filament\Resources\Repository\Pages\CreateRepository;
use App\Filament\Resources\Repository\Pages\EditRepository;
use App\Filament\Resources\Repository\Pages\ListRepositories;
use App\Filament\Resources\Repository\RelationManagers\CollaboratorsRelationManager;
use App\Filament\Resources\Repository\RelationManagers\FileLockRelationManager;
use App\Filament\Resources\Repository\RelationManagers\LfsObjectsRelationManager;
use App\Models\Repository;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RepositoryResource extends Resource
{
    protected static ?string $model = Repository::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static string|\UnitEnum|null $navigationGroup = 'Repositories';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('organization_id')
                ->relationship('organization', 'name')
                ->required()
                ->searchable(),
            Select::make('owner_id')
                ->relationship('owner', 'name')
                ->required()
                ->searchable(),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('slug')->required()->maxLength(100),
            Textarea::make('description')->nullable()->rows(3),
            Select::make('vcs_type')
                ->options(collect(VcsType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->required(),
            Select::make('visibility')
                ->options(collect(RepositoryVisibility::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                ->helperText('Visibility controls who can view and clone. Pushes still require an authorized account.')
                ->required(),
            TextInput::make('default_branch')->default('main'),
            Toggle::make('lfs_enabled')->label('LFS Enabled'),
            Toggle::make('is_archived')->label('Archived'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('organization.name')->label('Organization')->searchable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('vcs_type')->badge(),
                TextColumn::make('visibility')->badge(),
                IconColumn::make('lfs_enabled')->label('LFS')->boolean(),
                IconColumn::make('is_archived')->label('Archived')->boolean(),
                TextColumn::make('size_kb')->label('Size (KB)')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('vcs_type')->options(
                    collect(VcsType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                ),
                SelectFilter::make('visibility')->options(
                    collect(RepositoryVisibility::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                ),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepositories::route('/'),
            'create' => CreateRepository::route('/create'),
            'edit' => EditRepository::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            CollaboratorsRelationManager::class,
            FileLockRelationManager::class,
            LfsObjectsRelationManager::class,
        ];
    }
}
