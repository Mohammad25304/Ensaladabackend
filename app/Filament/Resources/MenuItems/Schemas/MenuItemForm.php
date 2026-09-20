<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Models\Branch;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
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
                ->label('Category')
                ->relationship(
                    name: 'category',
                    modifyQueryUsing: fn ($query) => $query->orderBy('sort_order'),
                )
                ->getOptionLabelFromRecordUsing(fn ($record) => $record->name['en'] ?? '(untitled)')
                ->getSearchResultsUsing(function (string $search) {
                    return Category::query()
                        ->whereRaw("name->>'en' ILIKE ?", ["%{$search}%"])
                        ->orderBy('sort_order')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn ($category) => [
                            $category->id => $category->name['en'] ?? '(untitled)',
                        ])
                        ->toArray();
                })
                ->required()
                ->searchable()
                ->preload(),

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

            TextInput::make('calories')
                ->label('Calories')
                ->numeric()
                ->minValue(0)
                ->suffix('kcal'),

            TextInput::make('protein_grams')
                ->label('Protein')
                ->numeric()
                ->minValue(0)
                ->suffix('g'),

            Toggle::make('is_featured')
                ->label('Show on homepage')
                ->default(false),

            Repeater::make('branchMenuItems')
                ->relationship()
                ->label('Available at branches')
                ->schema([
                    Select::make('branch_id')
                        ->label('Branch')
                        ->options(fn () => Branch::query()
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($branch) => [
                                $branch->id => $branch->name['en'] ?? '(untitled)',
                            ])
                            ->toArray())
                        ->required()
                        ->searchable()
                        ->distinct()
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

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
                ->columns(3)
                ->addActionLabel('Add a branch')
                ->defaultItems(0)
                ->helperText('Add one row per branch this item is offered at, with that branch\'s price. Leave a branch out entirely if it\'s not offered there.')
                ->columnSpanFull(),
        ])->columns(2);
    }
}
