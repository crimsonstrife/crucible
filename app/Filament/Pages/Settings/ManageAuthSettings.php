<?php

namespace App\Filament\Pages\Settings;

use App\Settings\AuthSettings;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Pages\SettingsPage;

class ManageAuthSettings extends SettingsPage
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-lock-closed';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Authentication';
    protected static ?int $navigationSort = 1;
    protected static string $settings = AuthSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('allowRegistration')->label('Allow Public Registration'),
            Toggle::make('requireEmailVerification')->label('Require Email Verification'),
        ]);
    }
}
