<?php

namespace App\Filament\Resources\MenuItems\Tables;


use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;


class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
          return $table
            ->columns([
                ImageColumn::make('image')
                    ->disk('public')
                    ->square(),
 
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
 
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->sortable(),
 
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
                    ->relationship('category', 'name')
                    ->label('Category'),
 
                TernaryFilter::make('is_featured')
                    ->label('Featured'),
 
                TernaryFilter::make('is_available')
                    ->label('Available'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
