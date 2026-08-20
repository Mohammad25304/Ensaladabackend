<?php

namespace App\Filament\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function  configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('50px'),
 
                TextColumn::make('name.en')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
 
                TextColumn::make('menu_items_count')
                    ->label('Items')
                    ->counts('menuItems')
                    ->badge(),
 
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
 
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
