<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\ServiceClients\Actions;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Api\CreateServiceClient;
use App\Filament\Administration\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use App\Filament\Administration\Resources\Users\ServiceClients\Schemas\ServiceClientSchemas;
use App\Filament\Support\RevealOnceSecret;
use Filament\Actions\Action;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;

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

        $secret = RevealOnceSecret::for('service_client:create');

        $this->authorize(fn (ServiceClientsRelationManager $livewire): bool => static::allowsCredential(CredentialOperation::Issue, CredentialKind::ServiceClient, $livewire->apiUser()))
            ->label('Create Service Client')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->outlined()
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->mountUsing($secret->mountFresh())
            ->steps([
                Wizard\Step::make('Configure')
                    ->schema([
                        ServiceClientSchemas::clientConfigurationSection(),
                    ])
                    ->afterValidation(fn (array $state, CreateServiceClient $createServiceClient, ServiceClientsRelationManager $livewire) => $secret->issueOnce(function () use ($state, $createServiceClient, $livewire): array {
                        $configuration = ServiceClientSchemas::normalizeConfigurationState($state);

                        [$plain, $client] = $createServiceClient(
                            apiUser: $livewire->apiUser(),
                            name: $configuration['name'],
                            secretExpiresAt: $configuration['secret_expires_at'],
                            allowedIps: $configuration['allowed_ips'],
                            createdBy: static::actingUser(),
                        );

                        return ['id' => $client->getKey(), 'secret' => $plain];
                    })),
                Wizard\Step::make('Copy Credentials')
                    ->schema(ServiceClientSchemas::copyCredentialsStepSchema($secret)),
            ])
            ->modalSubmitAction(fn (Action $action) => ServiceClientSchemas::copyCredentialsSubmitButton($action))
            ->action(fn () => $secret->forget())
            ->successNotificationTitle('Service Client Created');
    }
}
