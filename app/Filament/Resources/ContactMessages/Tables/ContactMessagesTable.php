<?php

namespace App\Filament\Resources\ContactMessages\Tables;


use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Models\ContactMessage;
use Filament\Tables\Columns\IconColumn ;


use Filament\Tables\Filters\TernaryFilter;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
          return $table
            ->columns([
                IconColumn::make('is_read')
                    ->label('Read')
                    ->boolean(),
 
                TextColumn::make('name')
                    ->searchable(),
 
                TextColumn::make('email')
                    ->searchable(),
 
                TextColumn::make('message')
                    ->limit(50)
                    ->wrap(),
 
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_read')
                    ->label('Read status'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ContactMessage $record) => static::getUrl('edit', ['record' => $record]));
    }
}
