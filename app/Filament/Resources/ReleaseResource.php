<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Release\Pages\ListReleases;
use App\Models\Release;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Site-wide admin view of releases across all repositories.
 *
 * Read + delete only. Authoring (create/edit) happens in the per-repository
 * Livewire UI by repo maintainers — admins use this resource for moderation
 * and takedowns, not content authoring.
 */
class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | \UnitEnum | null $navigationGroup = 'Repositories';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'tag_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('repository.organization.slug')
                    ->label('Org')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('repository.name')
                    ->label('Repository')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tag_name')
                    ->label('Tag')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Title')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_draft')->label('Draft')->boolean(),
                IconColumn::make('is_prerelease')->label('Pre')->boolean(),
                IconColumn::make('is_latest')->label('Latest')->boolean(),
                TextColumn::make('entries_count')
                    ->counts('entries')
                    ->label('Entries')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('repository_id')
                    ->relationship('repository', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Repository'),
                Filter::make('is_draft')
                    ->label('Drafts only')
                    ->query(fn (Builder $q) => $q->where('is_draft', true)),
                Filter::make('is_prerelease')
                    ->label('Pre-releases only')
                    ->query(fn (Builder $q) => $q->where('is_prerelease', true)),
                Filter::make('published')
                    ->label('Published only')
                    ->query(fn (Builder $q) => $q->where('is_draft', false)->whereNotNull('published_at')),
            ])
            ->actions([
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete release')
                    ->modalDescription(fn (Release $record) => "Soft-delete release {$record->tag_name} for {$record->repository?->name}? It will disappear from the public changelog API immediately."),
            ])
            ->bulkActions([
                DeleteBulkAction::make()->requiresConfirmation(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReleases::route('/'),
        ];
    }
}
