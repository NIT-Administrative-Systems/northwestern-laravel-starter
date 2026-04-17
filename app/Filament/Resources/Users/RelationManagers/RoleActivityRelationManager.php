<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Resources\RoleActivity\Tables\RoleActivityTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoleActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    protected static ?string $title = 'Role History';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return $ownerRecord->auth_type !== AuthType::API
            && auth()->user()?->hasPermissionTo(SystemPermission::ViewAuditLogs);
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        return Tab::make('Role History')
            ->icon(Heroicon::OutlinedClock);
    }

    public function table(Table $table): Table
    {
        return RoleActivityTable::configure($table, true)
            ->modifyQueryUsing(function (Builder $query) {
                return Audit::query()
                    ->roleActivity()
                    ->where('auditable_id', $this->getOwnerRecord()->getKey())
                    ->with(['user', 'impersonator', 'auditable']);
            })
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No role change history')
            ->emptyStateDescription('This user has no role assignment or removal events recorded.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
