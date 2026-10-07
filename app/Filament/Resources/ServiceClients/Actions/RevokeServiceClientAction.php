<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceClients\Actions;

use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
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
            ->modalHeading('Revoke Service Client')
            ->modalDescription('The service client and every access token it holds stop working immediately, and so does any integration still using it. This can\'t be undone.')
            ->modalSubmitActionLabel('Revoke Service Client')
            ->action(fn (OAuthClient $record, RevokeServiceClient $revokeServiceClient) => $revokeServiceClient($record, $this->administrator()))
            ->successNotificationTitle('Service Client Revoked')
            ->visible(fn (OAuthClient $record): bool => ServiceClientSchemas::isMutable($record));
    }

    private function administrator(): User
    {
        /** @var User */
        return auth()->user();
    }
}
