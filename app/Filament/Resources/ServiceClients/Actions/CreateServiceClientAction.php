<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceClients\Actions;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Domains\User\Models\User;
use App\Filament\Resources\ServiceClients\Schemas\ServiceClientSchemas;
use App\Filament\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Session;

class CreateServiceClientAction extends Action
{
    use AuthorizesCredentials;

    public static function getDefaultName(): ?string
    {
        return 'createServiceClient';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(fn (ServiceClientsRelationManager $livewire): bool => static::allowsCredential(CredentialOperation::Issue, CredentialKind::ServiceClient, $livewire->apiUser()))
            ->label('Create Service Client')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->outlined()
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->mountUsing(ServiceClientSchemas::mountFresh(ServiceClientSchemas::SESSION_KEY_CREATE))
            ->steps([
                Wizard\Step::make('Configure')
                    ->schema([
                        ServiceClientSchemas::clientConfigurationSection(),
                    ])
                    ->afterValidation(function (array $state, CreateServiceClient $createServiceClient, ServiceClientsRelationManager $livewire): void {
                        if (Session::has(ServiceClientSchemas::SESSION_KEY_CREATE)) {
                            return;
                        }

                        /** @var User $apiUser */
                        $apiUser = $livewire->getOwnerRecord();
                        $configuration = ServiceClientSchemas::normalizeConfigurationState($state);

                        [$secret, $client] = $createServiceClient(
                            apiUser: $apiUser,
                            name: $configuration['name'],
                            secretExpiresAt: $configuration['secret_expires_at'],
                            allowedIps: $configuration['allowed_ips'],
                            createdBy: static::actingUser(),
                        );

                        ServiceClientSchemas::storeCredentials(ServiceClientSchemas::SESSION_KEY_CREATE, $client, $secret);
                    }),
                Wizard\Step::make('Copy Credentials')
                    ->schema(ServiceClientSchemas::copyCredentialsStepSchema(ServiceClientSchemas::SESSION_KEY_CREATE)),
            ])
            ->modalSubmitAction(fn (Action $action) => ServiceClientSchemas::copyCredentialsSubmitButton($action))
            ->action(fn () => ServiceClientSchemas::clearCredentials(ServiceClientSchemas::SESSION_KEY_CREATE))
            ->successNotificationTitle('Service Client Created');
    }
}
