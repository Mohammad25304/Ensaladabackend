<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
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
                ->helperText('Used in the menu page URL, e.g. /menu/signature-bowls')
                ->columnSpanFull(),
 
            Textarea::make('description.en')
                ->label('Description (English)')
                ->rows(3),
 
            Textarea::make('description.es')
                ->label('Description (Spanish)')
                ->rows(3),
 
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first in the category tabs'),
 
            Toggle::make('is_active')
                ->label('Visible on website')
                ->default(true)
                ->helperText('Turn off to hide this category without deleting it'),
        ])->columns(2);
    }
}
