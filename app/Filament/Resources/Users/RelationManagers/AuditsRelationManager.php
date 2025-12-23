<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\User\Models\Audit;
use App\Domains\User\Models\User;
use App\Filament\Resources\Audits\AuditResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    protected static ?string $title = 'Audit Logs';

    protected static ?string $relatedResource = AuditResource::class;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return $ownerRecord->auth_type !== AuthTypeEnum::API;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var User $ownerRecord */
        return Tab::make('Audit Logs')
            ->icon(Heroicon::OutlinedExclamationTriangle);
    }

    public function table(Table $table): Table
    {
        return AuditResource::table($table)
            ->modifyQueryUsing(function (Builder $query) {
                return Audit::query()
                    ->where('user_id', $this->getOwnerRecord()->getKey())
                    ->with('impersonator');
            });
    }
}
