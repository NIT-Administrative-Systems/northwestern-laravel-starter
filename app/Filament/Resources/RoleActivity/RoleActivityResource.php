<?php

declare(strict_types=1);

namespace App\Filament\Resources\RoleActivity;

use App\Domains\User\Models\Audit;
use App\Filament\Resources\RoleActivity\Pages\ListRoleActivity;
use App\Filament\Resources\RoleActivity\Tables\RoleActivityTable;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoleActivityResource extends Resource
{
    protected static ?string $model = Audit::class;

    protected static ?string $slug = 'roles/activity';

    protected static ?string $navigationLabel = 'Role Activity';

    protected static bool $isGloballySearchable = false;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return RoleActivityTable::configure($table);
    }

    /** @return Builder<Audit> */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Audit> $query */
        $query = parent::getEloquentQuery();

        return $query
            ->roleActivity()
            ->with(['user', 'impersonator', 'auditable']);
    }

    /** @return array<string, \Filament\Resources\Pages\PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ListRoleActivity::route('/'),
        ];
    }
}
