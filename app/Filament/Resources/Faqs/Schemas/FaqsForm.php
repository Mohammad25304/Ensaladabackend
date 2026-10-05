<?php

namespace App\Filament\Resources\Faqs\Schemas;

use App\Models\Branch;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question')
                ->label('Topic')
                ->required()
                ->maxLength(255)
                ->helperText('Just a label for you, e.g. "Parking" or "Delivery". Customers never see it.')
                ->columnSpanFull(),

            TagsInput::make('keywords')
                ->required()
                ->placeholder('Type a word or phrase and press Enter')
                ->helperText('Words a customer might type. The chatbot gives this answer when any of them appears in a message, e.g. parking, car park, valet. Matching is by whole word and ignores plurals and capitals.')
                ->columnSpanFull(),

            Textarea::make('answer.en')
                ->label('Answer (English)')
                ->required()
                ->rows(4)
                ->maxLength(1000)
                ->helperText('Plain text. This is what the chatbot replies with.')
                ->columnSpanFull(),

            Textarea::make('answer.es')
                ->label('Answer (Spanish)')
                ->rows(4)
                ->maxLength(1000)
                ->helperText('Optional. The chatbot currently replies in English.')
                ->columnSpanFull(),

            Select::make('branch_id')
                ->label('Branch')
                ->options(fn () => Branch::orderBy('sort_order')->get()
                    ->mapWithKeys(fn (Branch $b) => [$b->id => $b->name['en'] ?? $b->slug])
                    ->all())
                ->placeholder('All branches')
                ->helperText('Leave empty to use this answer at every branch. A branch-specific answer wins over an all-branches one.'),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->helperText('If two answers match equally, the lower number wins'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Turn off to stop using this answer without deleting it')
                ->columnSpanFull(),
        ])->columns(2);
    }
}
