<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessTokens\Actions;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\AccessToken;
use App\Filament\Resources\AccessTokens\Schemas\AccessTokenSchemas;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

class RevokeAccessTokenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revokeAccessToken';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::MANAGE_API_USERS)
            ->label('Revoke')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->requiresConfirmation()
            ->modalHeading('Revoke Access Token')
            ->modalDescription('Are you sure you want to revoke this token? This action cannot be undone and should ONLY be done if the token was created in error or if credentials need to be rotated. Revoking a token in use can potentially cause a service outage for consumers.')
            ->modalSubmitActionLabel('Revoke Token')
            ->action(function (AccessToken $record) {
                $record->update([
                    'revoked_at' => now(),
                ]);
            })
            ->successNotificationTitle('Token revoked')
            ->visible(fn (AccessToken $record) => AccessTokenSchemas::isMutable($record));
    }
}
