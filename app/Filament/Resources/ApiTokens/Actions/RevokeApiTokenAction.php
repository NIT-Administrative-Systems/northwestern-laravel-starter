<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiTokens\Actions;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Models\ApiToken;
use App\Filament\Resources\ApiTokens\Schemas\ApiTokenSchemas;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

class RevokeApiTokenAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revokeApiToken';
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
            ->modalHeading('Revoke API Token')
            ->modalDescription('Are you sure you want to revoke this token? This action cannot be undone and should ONLY be done if the token was created in error or if credentials need to be rotated. Revoking a token in use can potentially cause a service outage for consumers.')
            ->modalSubmitActionLabel('Revoke Token')
            ->action(function (ApiToken $record) {
                $record->update([
                    'revoked_at' => now(),
                ]);
            })
            ->successNotificationTitle('Token revoked')
            ->visible(fn (ApiToken $record) => ApiTokenSchemas::isMutable($record));
    }
}
