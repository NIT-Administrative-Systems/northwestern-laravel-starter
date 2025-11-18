<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\UserLoginRecords\UserLoginRecordResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LoginRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'login_records';

    protected static ?string $relatedResource = UserLoginRecordResource::class;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var User $ownerRecord */
        return $ownerRecord->auth_type !== AuthTypeEnum::API;
    }

    public static function getTabComponent(Model $ownerRecord, string $pageClass): Tab
    {
        /** @var User $ownerRecord */
        return Tab::make('Login Records')
            ->icon(Heroicon::OutlinedPresentationChartLine);
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                //
            ]);
    }
}
