<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UsersForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('password')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->rule(Password::min(8)->mixedCase()->numbers())
                ->confirmed()
                ->dehydrated(fn ($state) => filled($state))
                ->helperText(fn (string $operation): string => $operation === 'create'
                    ? 'At least 8 characters, with upper/lowercase letters and a number.'
                    : 'Leave blank to keep the current password.'
                ),

            TextInput::make('password_confirmation')
                ->password()
                ->revealable()
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(false)
                ->label('Confirm password'),

            // Every admin has identical, full access — this is not
            // exposed as an editable field, just kept for future use.
            Hidden::make('role')->default('admin'),
        ])->columns(1);
    }
}
