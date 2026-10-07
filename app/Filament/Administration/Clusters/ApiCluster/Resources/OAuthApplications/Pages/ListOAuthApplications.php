<?php

declare(strict_types=1);

namespace App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications\Pages;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications\OAuthApplicationResource;
use App\Filament\Administration\Clusters\ApiCluster\Resources\OAuthApplications\Schemas\OAuthApplicationSchemas;
use App\Filament\Support\RevealOnceSecret;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;

class ListOAuthApplications extends ListRecords
{
    use AuthorizesCredentials;

    protected static string $resource = OAuthApplicationResource::class;

    protected ?string $subheading = 'External applications people can connect to their account';

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        $secret = RevealOnceSecret::for('application:register');

        return [
            Action::make('register')
                ->label('Register Application')
                ->visible(fn (): bool => static::allowsCredential(CredentialOperation::Issue, CredentialKind::ConnectedApplication))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->mountUsing($secret->mountFresh())
                ->steps([
                    Wizard\Step::make('Details')
                        ->schema([
                            ...OAuthApplicationSchemas::detailsFields(),
                            Toggle::make('confidential')
                                ->label('Confidential application')
                                ->helperText('On for applications with a server that can keep a secret. Off for desktop, mobile and browser applications, which use PKCE instead.')
                                ->default(true),
                        ])
                        ->afterValidation(fn (array $state, RegisterOAuthApplication $register) => $secret->issueOnce(function () use ($state, $register): array {
                            [$plain, $client] = $register(
                                $state['name'],
                                array_values($state['redirect_uris']),
                                (bool) $state['confidential'],
                                array_values($state['scopes'] ?? []),
                                (bool) $state['first_party'],
                                $state['description'] ?? null,
                                $state['contact_email'] ?? null,
                                registeredBy: static::actingUser(),
                            );

                            return ['id' => $client->getKey(), 'secret' => $plain];
                        })),
                    Wizard\Step::make('Credentials')->schema(OAuthApplicationSchemas::credentialsStep($secret)),
                ])
                ->modalSubmitActionLabel('Done')
                ->action(fn () => $secret->forget())
                ->successNotificationTitle('Application Registered'),
        ];
    }
}
