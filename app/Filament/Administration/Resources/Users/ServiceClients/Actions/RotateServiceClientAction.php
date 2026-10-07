<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\ServiceClients\Actions;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Api\RotateServiceClient;
use App\Domains\Auth\Models\OAuthClient;
use App\Filament\Administration\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use App\Filament\Administration\Resources\Users\ServiceClients\Schemas\ServiceClientSchemas;
use App\Filament\Support\RevealOnceSecret;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class RotateServiceClientAction extends Action
{
    use AuthorizesCredentials;

    public static function getDefaultName(): ?string
    {
        return 'rotateServiceClient';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $secret = RevealOnceSecret::for('service_client:rotate');

        $this->authorize(fn (ServiceClientsRelationManager $livewire): bool => static::allowsCredential(CredentialOperation::Modify, CredentialKind::ServiceClient, $livewire->apiUser()))
            ->label('Rotate')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->mountUsing($secret->mountFresh())
            ->steps([
                Wizard\Step::make('Rotate Service Client')
                    ->schema([
                        Section::make('Before You Rotate')
                            ->icon(Heroicon::OutlinedInformationCircle)
                            ->schema([
                                TextEntry::make('rotate_notice')
                                    ->hiddenLabel()
                                    ->default(new HtmlString(<<<'HTML'
Rotating creates a replacement service client with a new ID and secret. <strong>This one keeps working</strong>, so the integration can switch without downtime. Revoke this one once the integration uses the replacement.
HTML))
                                    ->columnSpanFull(),
                            ]),
                        ServiceClientSchemas::clientConfigurationSection(),
                    ])
                    ->afterValidation(fn (array $state, RotateServiceClient $rotateServiceClient, OAuthClient $record) => $secret->issueOnce(function () use ($state, $rotateServiceClient, $record): array {
                        $configuration = ServiceClientSchemas::normalizeConfigurationState($state);

                        [$plain, $replacement] = $rotateServiceClient(
                            previous: $record,
                            rotatedBy: static::actingUser(),
                            name: $configuration['name'],
                            secretExpiresAt: $configuration['secret_expires_at'],
                            allowedIps: $configuration['allowed_ips'],
                        );

                        return ['id' => $replacement->getKey(), 'secret' => $plain];
                    }, scope: $record)),
                Wizard\Step::make('Copy Credentials')
                    ->schema(ServiceClientSchemas::copyCredentialsStepSchema($secret)),
            ])
            ->modalSubmitAction(fn (Action $action) => ServiceClientSchemas::copyCredentialsSubmitButton($action))
            ->action(fn () => $secret->forget())
            ->successNotificationTitle('Replacement Service Client Created')
            // An active client, or the one whose rotation is in progress, so the wizard can finish even if
            // the client changed state meanwhile.
            ->visible(fn (OAuthClient $record): bool => ServiceClientSchemas::isMutable($record) || $secret->isFor($record));
    }
}
