<?php

namespace App\Filament\Pages\Settings;

use App\Settings\ForgeIntegrationSettings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Pages\SettingsPage;

class ManageForgeIntegrationSettings extends SettingsPage
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-link';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Forge Integration';
    protected static ?int $navigationSort = 4;
    protected static string $settings = ForgeIntegrationSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('enabled')->label('Enable Forge Integration'),
            TextInput::make('url')->label('Forge URL')->url()->nullable(),
            TextInput::make('token')->label('API Token')->password()->nullable(),
            TextInput::make('clientId')->label('OAuth Client ID')->nullable(),
            TextInput::make('clientSecret')->label('OAuth Client Secret')->password()->nullable(),
            TextInput::make('redirectUri')->label('Redirect URI')->url()->nullable(),
        ]);
    }
}
