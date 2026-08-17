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
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, callable $set) {
                    // Auto-fill the slug from the name, but only when creating
                    // (so editing the name later doesn't silently break existing links)
                    if ($operation === 'create') {
                        $set('slug', Str::slug($state));
                    }
                }),
 
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Used in the menu page URL, e.g. /menu/signature-bowls'),
 
            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull()
                ->helperText('Shown under the category name on the menu page'),
 
            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first in the category tabs'),
 
            Toggle::make('is_active')
                ->label('Visible on website')
                ->default(true)
                ->helperText('Turn off to hide this category without deleting it'),
        ]);
    }
}
