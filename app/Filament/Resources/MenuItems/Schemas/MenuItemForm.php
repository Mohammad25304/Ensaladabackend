<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

use Illuminate\Support\Str;
class MenuItemForm
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
                ->columnSpanFull(),
 
            Select::make('category_id')
                ->relationship('category', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->name['en'] ?? '(untitled)')
                ->required()
                ->searchable()
                ->preload(),
 
            TextInput::make('price')
                ->required()
                ->numeric()
                ->prefix('$')
                ->step(0.01),
 
            Select::make('tags')
                ->relationship('tags', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')->required(),
                ])
                ->helperText('e.g. Vegan, Gluten-Free, High-Protein'),
 
            Textarea::make('description.en')
                ->label('Description / Ingredients (English)')
                ->required()
                ->rows(4),
 
            Textarea::make('description.es')
                ->label('Description / Ingredients (Spanish)')
                ->required()
                ->rows(4),
 
            FileUpload::make('image')
                ->image()
                ->required()
                ->disk('public')
                ->directory('menu-items')
                ->imageEditor()
                ->imagePreviewHeight('200')
                ->maxSize(5120)
                ->columnSpanFull(),
 
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
 
            Toggle::make('is_featured')
                ->label('Show on homepage')
                ->default(false),
 
            Toggle::make('is_available')
                ->label('Available')
                ->default(true)
                ->helperText('Turn off to hide temporarily without deleting'),
        ])->columns(2);
    }
}
