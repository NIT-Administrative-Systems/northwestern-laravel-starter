<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Announcements;

use App\Domains\Support\Enums\AnnouncementAudience;
use App\Domains\Support\Models\Announcement;
use App\Filament\Administration\Navigation\AdministrationNavGroup;
use App\Filament\Administration\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Administration\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Administration\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Administration\Resources\Announcements\Schemas\AnnouncementForm;
use App\Filament\Administration\Resources\Announcements\Tables\AnnouncementsTable;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Announcements shown to the application's users in a banner and on their Announcements page.
 * Managed by people with `ManageAnnouncements` ({@see \App\Domains\Support\Policies\AnnouncementPolicy}).
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::Platform;

    protected static ?int $navigationSort = 5;

    protected static ?string $description = 'Messages shown to the application\'s users in a banner and on their Announcements page.';

    public static function form(Schema $schema): Schema
    {
        return AnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnouncementsTable::configure($table);
    }

    /**
     * Only a targeted announcement keeps its roles and affiliations.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeAudience(array $data): array
    {
        $audience = $data['audience'] ?? null;
        $targeted = $audience === AnnouncementAudience::Targeted || $audience === AnnouncementAudience::Targeted->value;

        return [
            ...$data,
            'role_ids' => $targeted ? array_values(array_map('intval', $data['role_ids'] ?? [])) : null,
            'affiliations' => $targeted ? array_values($data['affiliations'] ?? []) : null,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
