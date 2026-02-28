<?php

declare(strict_types=1);

namespace App\Filament\Resources\SupportTickets;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Support\Models\SupportTicket;
use App\Filament\Navigation\AdministrationNavGroup;
use App\Filament\Resources\SupportTickets\Schemas\SupportTicketInfolist;
use App\Filament\Resources\SupportTickets\Tables\SupportTicketsTable;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::PLATFORM;

    protected static ?int $navigationSort = 10;

    protected static ?string $description = 'Read-only submission log for user-submitted support tickets.';

    public static function canAccess(): bool
    {
        return config('support.enabled')
            && auth()->user()?->can(PermissionEnum::VIEW_SUPPORT_TICKETS);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportTicketInfolist::configure($schema);
    }

    public static function getNavigationBadge(): ?string
    {
        $errorCount = Cache::flexible('nav_badge_support_errors_count', [30, 60], fn () => SupportTicket::where('post_error', true)->count());

        return $errorCount > 0 ? (string) $errorCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getGlobalSearchResultTitle(Model $record): string|Htmlable
    {
        /** @var SupportTicket $record */
        return $record->subject;
    }

    /** @return array<string, string|null> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var SupportTicket $record */
        return array_filter([
            'Ticket' => $record->ticket_number,
            'Submitted by' => $record->user?->full_name,
            'Email' => $record->requester_email,
        ]);
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'subject',
            'ticket_number',
            'requester_email',
            'user.first_name',
            'user.last_name',
            'user.username',
        ];
    }

    /** @return Builder<SupportTicket> */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('user');
    }

    /** @return Builder<SupportTicket> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
        ];
    }
}
