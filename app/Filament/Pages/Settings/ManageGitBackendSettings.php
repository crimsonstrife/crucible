<?php

namespace App\Filament\Pages\Settings;

use App\Settings\GitBackendSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Pages\SettingsPage;

class ManageGitBackendSettings extends SettingsPage
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-server';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Git Backend';
    protected static ?int $navigationSort = 2;
    protected static string $settings = GitBackendSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('backend')
                ->options(['stub' => 'Stub (No-op)', 'native' => 'Native Git', 'service' => 'External Service'])
                ->required(),
            TextInput::make('serviceUrl')->label('Service URL')->url()->nullable(),
            TextInput::make('serviceToken')->label('Service Token')->password()->nullable(),
            TextInput::make('reposPath')->label('Repositories Path')->nullable(),
            TextInput::make('defaultBranch')->label('Default Branch')->default('main'),
            TextInput::make('maxRepoSizeMb')->label('Max Repo Size (MB)')->numeric(),
        ]);
    }
}
