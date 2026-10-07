<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Announcements\Pages;

use App\Filament\Administration\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Writes a new announcement as a draft. It's published from its edit page.
 */
class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...AnnouncementResource::normalizeAudience($data), 'created_by_user_id' => auth()->id()];
    }

    protected function getCreatedNotificationTitle(): string
    {
        return 'Draft Saved';
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
