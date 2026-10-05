<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceClients\Actions;

use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Filament\Resources\ServiceClients\Schemas\ServiceClientSchemas;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

class RevokeServiceClientAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'revokeServiceClient';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(SystemPermission::ManageApiAccess)
            ->label('Revoke')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->requiresConfirmation()
            ->modalHeading('Revoke Client')
            ->modalDescription('The client and every access token it holds stop working immediately. This can\'t be undone. Revoking a client that an integration still uses will cause an outage for it.')
            ->modalSubmitActionLabel('Revoke Client')
            ->action(fn (OAuthClient $record, RevokeServiceClient $revokeServiceClient) => $revokeServiceClient($record))
            ->successNotificationTitle('Client revoked')
            ->visible(fn (OAuthClient $record): bool => ServiceClientSchemas::isMutable($record));
    }
}
