<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\Actions\SendLoginLinkAction;
use App\Filament\Resources\Users\Schemas\ApiUserInfolist;
use App\Filament\Resources\Users\Schemas\LocalUserInfolist;
use App\Filament\Resources\Users\Schemas\NorthwesternUserInfolist;
use App\Filament\Resources\Users\UserResource;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor\Actions\LinkAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function infolist(Schema $schema): Schema
    {
        /** @var User $record */
        $record = $this->getRecord();

        $schema->record($record);

        return match ($record->auth_type) {
            AuthTypeEnum::SSO => NorthwesternUserInfolist::configure($schema),
            AuthTypeEnum::LOCAL => LocalUserInfolist::configure($schema),
            AuthTypeEnum::API => ApiUserInfolist::configure($schema),
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            SendLoginLinkAction::make(),
            LinkAction::make()
                ->name('impersonate')
                ->color('warning')
                ->hidden(fn (User $record) => ! Filament::auth()->user()->canImpersonateUser($record))
                ->label('Impersonate')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (User $user): string => route('impersonate', [$user])),
        ];
    }
}
