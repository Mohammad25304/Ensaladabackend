<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageSiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.manage-site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        // Pre-fill the form with whatever is already saved in site_settings
        $this->form->fill(SiteSetting::allAsArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero Section')
                    ->schema([
                        TextInput::make('hero_badge')->label('Badge text'),
                        TextInput::make('hero_title')->label('Title'),
                        Textarea::make('hero_subtitle')->label('Subtitle')->rows(2),
                    ])
                    ->columns(1),

                Section::make('About Section')
                    ->schema([
                        TextInput::make('about_title')->label('Title'),
                        Textarea::make('about_body_1')->label('Paragraph 1')->rows(3),
                        Textarea::make('about_body_2')->label('Paragraph 2')->rows(3),
                    ])
                    ->columns(1),

                Section::make('Stats')
                    ->schema([
                        TextInput::make('stat_1_value')->label('Stat 1 value'),
                        TextInput::make('stat_1_label')->label('Stat 1 label'),
                        TextInput::make('stat_2_value')->label('Stat 2 value'),
                        TextInput::make('stat_2_label')->label('Stat 2 label'),
                        TextInput::make('stat_3_value')->label('Stat 3 value'),
                        TextInput::make('stat_3_label')->label('Stat 3 label'),
                    ])
                    ->columns(2),

                Section::make('Contact Info')
                    ->schema([
                        TextInput::make('contact_address')->label('Address'),
                        TextInput::make('contact_phone')->label('Phone'),
                        TextInput::make('contact_email')->label('Email')->email(),
                        TextInput::make('hours_weekday')->label('Weekday hours'),
                        TextInput::make('hours_weekend')->label('Weekend hours'),
                    ])
                    ->columns(2),

                Section::make('Social Links')
                    ->schema([
                        TextInput::make('social_instagram')->label('Instagram URL')->url(),
                        TextInput::make('social_facebook')->label('Facebook URL')->url(),
                        TextInput::make('social_twitter')->label('Twitter/X URL')->url(),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            SiteSetting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}