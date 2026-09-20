<?php

namespace App\Filament\Resources\MenuItems\RelationManagers;

use App\Models\Branch;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Branches';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn ($record) => $record->name['en'] ?? '(untitled)')
            ->columns([
                TextColumn::make('name.en')
                    ->label('Branch'),

                TextColumn::make('pivot.price')
                    ->label('Price')
                    ->money('usd'),

                IconColumn::make('pivot.is_available')
                    ->label('Available')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add to a branch')
                    ->recordSelect(fn ($select) => $select
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name['en'] ?? '(untitled)'))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->step(0.01),
                        Toggle::make('is_available')
                            ->label('Available')
                            ->default(true),
                    ])
                    ->after(fn () => Branch::clearCaches()),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->step(0.01),
                        Toggle::make('is_available')
                            ->label('Available')
                            ->default(true),
                    ])
                    ->after(fn () => Branch::clearCaches()),
                DetachAction::make()
                    ->label('Remove from branch')
                    ->after(fn () => Branch::clearCaches()),
            ]);
    }
}
