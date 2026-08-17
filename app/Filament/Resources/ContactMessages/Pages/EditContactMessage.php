<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContactMessage extends EditRecord
{
   protected static string $resource = ContactMessageResource::class;
 
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
 
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Mark as read the moment the admin opens this message
        if (! $this->record->is_read) {
            $this->record->update(['is_read' => true]);
        }
 
        return $data;
    }
}
