<?php

namespace App\Filament\Resources\Faqs\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FaqsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Topic')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('keywords')
                    ->badge()
                    ->color('gray')
                    ->limitList(4),

                TextColumn::make('answer.en')
                    ->label('Answer')
                    ->limit(60)
                    ->toggleable(),

                TextColumn::make('branch.name.en')
                    ->label('Branch')
                    ->placeholder('All branches'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
