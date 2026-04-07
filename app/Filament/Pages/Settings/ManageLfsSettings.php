<?php

namespace App\Filament\Pages\Settings;

use App\Settings\LfsSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Pages\SettingsPage;

class ManageLfsSettings extends SettingsPage
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'LFS Storage';
    protected static ?int $navigationSort = 3;
    protected static string $settings = LfsSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('enabled')->label('Enable LFS'),
            Select::make('storageDisk')
                ->label('Storage Disk')
                ->options(['local' => 'Local', 's3' => 'Amazon S3', 'gcs' => 'Google Cloud Storage']),
            TextInput::make('maxObjectSizeMb')->label('Max Object Size (MB)')->numeric(),
        ]);
    }
}
