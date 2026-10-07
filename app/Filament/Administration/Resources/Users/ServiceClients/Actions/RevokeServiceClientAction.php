<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\ServiceClients\Actions;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Api\RevokeServiceClient;
use App\Domains\Auth\Models\OAuthClient;
use App\Filament\Administration\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use App\Filament\Administration\Resources\Users\ServiceClients\Schemas\ServiceClientSchemas;
use Filament\Actions\Action;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

class RevokeServiceClientAction extends Action
{
    use AuthorizesCredentials;

    public static function getDefaultName(): ?string
    {
        return 'revokeServiceClient';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(fn (ServiceClientsRelationManager $livewire): bool => static::allowsCredential(CredentialOperation::Revoke, CredentialKind::ServiceClient, $livewire->apiUser()))
            ->label('Revoke')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->requiresConfirmation()
            ->modalHeading('Revoke Service Client')
            ->modalDescription('The service client and every access token it holds stop working immediately, and so does any integration still using it. This can\'t be undone.')
            ->modalSubmitActionLabel('Revoke Service Client')
            ->action(fn (OAuthClient $record, RevokeServiceClient $revokeServiceClient) => $revokeServiceClient($record, static::actingUser()))
            ->successNotificationTitle('Service Client Revoked')
            ->visible(fn (OAuthClient $record): bool => ServiceClientSchemas::isMutable($record));
    }
}
