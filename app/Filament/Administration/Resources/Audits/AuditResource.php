<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Audits;

use App\Domains\Core\Models\Audit;
use App\Filament\Administration\Navigation\AdministrationNavGroup;
use App\Filament\Administration\Resources\Audits\Pages\ListAudits;
use App\Filament\Administration\Resources\Audits\Pages\ViewAudit;
use App\Filament\Administration\Resources\Audits\Schemas\AuditInfolist;
use App\Filament\Administration\Resources\Audits\Tables\AuditsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class AuditResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $navigationLabel = 'Audit Logs';

    protected static string|null|UnitEnum $navigationGroup = AdministrationNavGroup::Platform;

    protected static ?int $navigationSort = 3;

    /**
     * Decided by the audit policy, named here so the audit log never falls back to Filament's
     * default of letting every administrator in.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Audit::class);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuditInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAudits::route('/'),
            'view' => ViewAudit::route('/{record}'),
        ];
    }
}
