<?php

namespace App\Filament\Resources\Branches\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BranchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name.en')
                ->label('Name (English)')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, callable $set) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug($state));
                    }
                }),

            TextInput::make('name.es')
                ->label('Name (Spanish)')
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Used in the app to identify this branch, e.g. "beirut". Auto-filled from the English name.')
                ->columnSpanFull(),

            Textarea::make('address')
                ->rows(2)
                ->columnSpanFull(),

            TextInput::make('phone')
                ->tel()
                ->maxLength(50),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers show first in the branch picker'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Turn off to hide this branch from the picker without deleting it')
                ->columnSpanFull(),
        ])->columns(2);
    }
}
