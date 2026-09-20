<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\SiteSetting;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageSiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.manage-site-settings';

    public ?array $data = [];

    /**
     * Which branch's settings are currently loaded in the form. Plain
     * Livewire property (not part of the Filament schema) so switching it
     * can trigger a full reload of $data without fighting the form's own
     * state management.
     */
    public ?int $branchId = null;

    /**
     * Platform options the owner can choose from. The frontend maps these
     * exact keys to icons — adding a new option here is safe even without
     * a matching icon, since the frontend falls back to a generic link icon.
     */
    public static function platformOptions(): array
    {
        return [
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'twitter' => 'Twitter / X',
            'tiktok' => 'TikTok',
            'youtube' => 'YouTube',
            'linkedin' => 'LinkedIn',
            'pinterest' => 'Pinterest',
            'reddit' => 'Reddit',
            'whatsapp' => 'WhatsApp',
            'snapchat' => 'Snapchat',
        ];
    }

    public function branchOptions(): array
    {
        return Branch::query()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($branch) => [$branch->id => $branch->name['en'] ?? '(untitled)'])
            ->toArray();
    }

    /**
     * Full branch records (not just id => label) for the pill switcher,
     * so it can render each branch's real name.
     */
    public function branches()
    {
        return Branch::query()->orderBy('sort_order')->get();
    }

    public function selectBranch(int $branchId): void
    {
        $this->branchId = $branchId;
        $this->loadSettingsForCurrentBranch();
    }

    public function mount(): void
    {
        // Default to the first branch so the page always has something
        // loaded, rather than showing an empty form on first visit.
        $this->branchId = Branch::orderBy('sort_order')->value('id');
        $this->loadSettingsForCurrentBranch();
    }

    /**
     * Called automatically by Livewire whenever $branchId changes via the
     * branch <select> in the Blade view (wire:model.live="branchId").
     */
    public function updatedBranchId(): void
    {
        $this->loadSettingsForCurrentBranch();
    }

    protected function loadSettingsForCurrentBranch(): void
    {
        if (! $this->branchId) {
            $this->form->fill([]);

            return;
        }

        $settings = SiteSetting::allAsArray($this->branchId);

        // social_links is stored as a JSON string in the database (since every
        // other setting is a plain string) — decode it into an array of rows
        // for the Repeater. Anything malformed/missing becomes an empty list.
        $settings['social_links'] = json_decode($settings['social_links'] ?? '[]', true) ?: [];

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hero Section')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->schema([
                        TextInput::make('hero_badge')->label('Badge text'),
                        TextInput::make('hero_title')->label('Title'),
                        Textarea::make('hero_subtitle')->label('Subtitle')->rows(2),
                    ])
                    ->columns(1),

                Section::make('About Section')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->schema([
                        TextInput::make('about_title')->label('Title'),
                        Textarea::make('about_body_1')->label('Paragraph 1')->rows(3),
                        Textarea::make('about_body_2')->label('Paragraph 2')->rows(3),
                    ])
                    ->columns(1),

                Section::make('Stats')
                    ->icon(Heroicon::OutlinedChartBar)
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
                    ->icon(Heroicon::OutlinedMapPin)
                    ->schema([
                        TextInput::make('contact_address')->label('Address'),
                        TextInput::make('contact_phone')->label('Phone'),
                        TextInput::make('contact_email')->label('Email')->email(),
                        TextInput::make('hours_weekday')->label('Weekday hours'),
                        TextInput::make('hours_weekend')->label('Weekend hours'),
                    ])
                    ->columns(2),

                Section::make('Social Links')
                    ->icon(Heroicon::OutlinedShare)
                    ->description('Add, remove, or reorder any platform — changes appear on the website immediately.')
                    ->schema([
                        Repeater::make('social_links')
                            ->hiddenLabel()
                            ->schema([
                                Select::make('platform')
                                    ->options(self::platformOptions())
                                    ->required()
                                    ->searchable(),
                                TextInput::make('url')
                                    ->label('Profile URL')
                                    ->url()
                                    ->required()
                                    ->placeholder('https://...'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add a social link')
                            ->reorderable()
                            ->reorderableWithDragAndDrop()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => self::platformOptions()[$state['platform'] ?? null] ?? 'New link')
                            ->defaultItems(0),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! $this->branchId) {
            Notification::make()
                ->title('Choose a branch first')
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        // Encode social_links back to a JSON string before saving, since
        // every site_settings row stores a plain string value.
        $data['social_links'] = json_encode($data['social_links'] ?? []);

        foreach ($data as $key => $value) {
            SiteSetting::set($key, $value, $this->branchId);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
