<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
       return $schema->components([
            TextInput::make('name')->disabled(),
            TextInput::make('email')->disabled(),
            TextInput::make('phone')->disabled(),
            Textarea::make('message')
                ->disabled()
                ->rows(5)
                ->columnSpanFull(),
        ])->columns(2);
    }
}
