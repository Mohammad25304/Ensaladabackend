<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Models\Branch;
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
            ->modifyQueryUsing(fn ($query) => $query->with('branches'))
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

                TextColumn::make('branches')
                    ->label('Offered at')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->branches
                        ->map(fn ($branch) => ($branch->name['en'] ?? '?').' ($'.number_format($branch->pivot->price, 2).')')
                        ->all())
                    ->placeholder('Not assigned to any branch yet'),

                IconColumn::make('is_featured')
                    ->label('Featured')
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

                SelectFilter::make('branches')
                    ->label('Branch')
                    ->options(fn () => Branch::query()
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn ($branch) => [
                            $branch->id => $branch->name['en'] ?? '(untitled)',
                        ])
                        ->toArray())
                    ->query(function ($query, array $data) {
                        if (filled($data['value'] ?? null)) {
                            $query->whereHas('branches', fn ($q) => $q->where('branches.id', $data['value']));
                        }
                    }),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
