<?php

namespace App\Filament\Resources\Organization\RelationManagers;

use App\Models\User;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Members';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('role')
                ->options([
                    'owner' => 'Owner',
                    'admin' => 'Admin',
                    'member' => 'Member',
                ])
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('pivot.role')->badge()->label('Role'),
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->label('Add Member')
                    ->recordSelectOptionsQuery(fn () => User::query()->orderBy('name'))
                    ->preloadRecordSelect()
                    ->schema([
                        Select::make('role')
                            ->options([
                                'owner' => 'Owner',
                                'admin' => 'Admin',
                                'member' => 'Member',
                            ])
                            ->required(),
                    ])
                    ->using(function ($relationship, array $data): void {
                        if ($recordId = $data['recordId'] ?? null) {
                            $relationship->attach([$recordId => ['role' => $data['role']]]);
                        }
                    }),
            ])
            ->recordActions([
                Actions\Action::make('editRole')
                    ->label('Edit Role')
                    ->icon('heroicon-m-pencil-square')
                    ->form([
                        Select::make('role')
                            ->options([
                                'owner' => 'Owner',
                                'admin' => 'Admin',
                                'member' => 'Member',
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $this->getRelationship()->updateExistingPivot($record->getKey(), [
                            'role' => $data['role'],
                        ]);
                    }),
                Actions\DetachAction::make()->label('Remove'),
            ]);
    }
}
