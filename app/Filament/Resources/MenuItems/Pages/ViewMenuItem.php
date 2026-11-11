<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMenuItem extends ViewRecord
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $raw = $this->record->getRawOriginal('image');

        // Only hand Filament a real storage path. Images stored as full URLs
        // (e.g. set through the API) aren't on the public disk, so leave the
        // field empty for those.
        $data['image'] = ($raw && ! str_starts_with($raw, 'http')) ? $raw : null;

        return $data;
    }
}
