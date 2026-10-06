<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthApplications\Pages;

use App\Domains\Auth\Actions\Applications\RegisterOAuthApplication;
use App\Filament\Resources\OAuthApplications\OAuthApplicationResource;
use App\Filament\Resources\OAuthApplications\Schemas\OAuthApplicationSchemas;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Session;

class ListOAuthApplications extends ListRecords
{
    protected static string $resource = OAuthApplicationResource::class;

    protected ?string $subheading = 'External applications people can connect to their account';

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('register')
                ->label('Register Application')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->steps([
                    Wizard\Step::make('Details')
                        ->schema([
                            ...OAuthApplicationSchemas::detailsFields(),
                            Toggle::make('confidential')
                                ->label('Confidential Application')
                                ->helperText('On for applications with a server that can keep a secret. Off for desktop, mobile and browser applications, which use PKCE instead.')
                                ->default(true),
                        ])
                        ->afterValidation(function (array $state, RegisterOAuthApplication $register): void {
                            if (Session::has(OAuthApplicationSchemas::SESSION_KEY)) {
                                return;
                            }

                            [$secret, $client] = $register(
                                $state['name'],
                                array_values($state['redirect_uris']),
                                (bool) $state['confidential'],
                                array_values($state['scopes'] ?? []),
                                (bool) $state['first_party'],
                                $state['description'] ?? null,
                                $state['contact_email'] ?? null,
                            );

                            OAuthApplicationSchemas::storeCredentials($client, $secret);
                        }),
                    Wizard\Step::make('Credentials')->schema(OAuthApplicationSchemas::credentialsStep()),
                ])
                ->modalSubmitActionLabel('Done')
                ->action(fn () => OAuthApplicationSchemas::clearCredentials())
                ->successNotificationTitle('Application Registered'),
        ];
    }
}
