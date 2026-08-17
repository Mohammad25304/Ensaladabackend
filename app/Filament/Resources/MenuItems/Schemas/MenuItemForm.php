<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
    return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $operation, $state, callable $set) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug($state));
                    }
                })
                ->columnSpan(2),
 
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
 
            Select::make('category_id')
                ->relationship('category', 'name')
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
 
            Textarea::make('description')
                ->required()
                ->rows(4)
                ->columnSpanFull()
                ->helperText('This also serves as the ingredients list shown to customers'),
 
            FileUpload::make('image')
                ->image()
                ->required()
                ->disk('public')
                ->directory('menu-items')
                ->imageEditor()
                ->imagePreviewHeight('200')
                ->maxSize(5120) // 5MB, matches backend validation
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
