<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Auth;

use App\Filament\App\Pages\Concerns\HasSiteHeader;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

/**
 * Lists the sign-in methods this application has configured: Northwestern single
 * sign-on (WebSSO, or Entra ID when WebSSO is not configured) and email login codes.
 *
 * When single sign-on is the only method, guests go straight to it, except in CI,
 * where the page always renders so end-to-end tests can use email login codes.
 */
class Login extends SimplePage
{
    use HasSiteHeader;

    private const string AUTH_DOCS_URL = 'https://laravel-starter.entapp.northwestern.edu/getting-started/installation/#5-environment-configuration';

    protected static ?string $title = 'Sign in';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect('/');

            return;
        }

        $ssoUrl = $this->ssoUrl();

        if (! $this->localAuthEnabled() && ! App::environment('ci') && $ssoUrl !== null) {
            $this->redirect($ssoUrl);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Sign in to ' . config('app.name');
    }

    public function content(Schema $schema): Schema
    {
        $ssoUrl = $this->ssoUrl();
        $localAuthEnabled = $this->localAuthEnabled();

        return $schema
            ->components([
                Section::make('Northwestern community')
                    ->description('For students, faculty, staff, and affiliates with a NetID.')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->iconColor('primary')
                    ->compact()
                    ->extraAttributes(['class' => 'nu-sign-in-option'])
                    ->schema([
                        Actions::make([
                            Action::make('netid')
                                ->label('Sign in with NetID')
                                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                                ->url($ssoUrl)
                                ->extraAttributes(['data-cy' => 'netid-login']),
                        ])->fullWidth(),
                    ])
                    ->visible($ssoUrl !== null),

                Section::make('External partners')
                    ->description('For approved external users and partners who do not have a NetID.')
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->iconColor('primary')
                    ->compact()
                    ->extraAttributes(['class' => 'nu-sign-in-option'])
                    ->schema([
                        Actions::make([
                            Action::make('email')
                                ->label('Sign in with email')
                                ->icon(Heroicon::OutlinedEnvelope)
                                ->color('gray')
                                ->outlined()
                                ->url(fn (): ?string => Route::has('filament.app.auth.login-code') ? route('filament.app.auth.login-code') : null)
                                ->extraAttributes(['data-cy' => 'email-login']),
                        ])->fullWidth(),
                    ])
                    ->visible($localAuthEnabled),

                Callout::make('No sign-in methods available')
                    ->description('This application has not been configured with any authentication providers yet.')
                    ->warning()
                    ->actions([
                        Action::make('docs')
                            ->label('Authentication documentation')
                            ->url(self::AUTH_DOCS_URL, shouldOpenInNewTab: true)
                            ->link(),
                    ])
                    ->visible($ssoUrl === null && ! $localAuthEnabled),
            ]);
    }

    private function localAuthEnabled(): bool
    {
        return (bool) config('local-auth.enabled');
    }

    private function ssoUrl(): ?string
    {
        $webssoConfigured = filled(config('nusoa.sso.apigeeApiKey'))
            || config('nusoa.sso.strategy') === 'forgerock-direct';

        $entraConfigured = filled(config('services.northwestern-azure.client_id'))
            && filled(config('services.northwestern-azure.client_secret'));

        return match (true) {
            $webssoConfigured => route('login-websso'),
            $entraConfigured => route('login-oauth-redirect'),
            default => null,
        };
    }
}
