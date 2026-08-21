<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Models\Category;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->disk('public')
                    ->square(),

                TextColumn::make('name.en')
                    ->label('Name')
                    ->searchable()
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw("name->>'en' {$direction}")),

                TextColumn::make('category.name.en')
                    ->label('Category')
                    ->badge(),

                TextColumn::make('price')
                    ->money('usd')
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                IconColumn::make('is_available')
                    ->label('Available')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::query()
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($category) => [
                            $category->id => $category->name['en'] ?? '(untitled)',
                        ])
                        ->toArray()),

                TernaryFilter::make('is_featured')
                    ->label('Featured'),

                TernaryFilter::make('is_available')
                    ->label('Available'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
