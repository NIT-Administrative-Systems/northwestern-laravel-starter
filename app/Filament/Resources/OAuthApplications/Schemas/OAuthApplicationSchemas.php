<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthApplications\Schemas;

use App\Filament\Support\RevealOnceSecret;
use App\Providers\OAuthServiceProvider;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Northwestern\SysDev\Chassis\Rules\OAuthRedirectUri;

/**
 * Form fields and the one-time credentials step for registering OAuth applications.
 */
class OAuthApplicationSchemas
{
    /**
     * @return list<Component>
     */
    public static function detailsFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->helperText('Shown to people when the application asks to connect.')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->label('Description')
                ->helperText('Optional. What the application does, shown on the consent screen.')
                ->rows(2)
                ->maxLength(1000),
            TextInput::make('contact_email')
                ->label('Contact Email')
                ->helperText('Who to contact about the application.')
                ->email()
                ->maxLength(255),
            TagsInput::make('redirect_uris')
                ->label('Redirect URIs')
                ->placeholder('https://app.example.edu/oauth/callback')
                ->helperText('Where people return after approving. HTTPS, or HTTP to localhost for applications on the user\'s own computer.')
                ->required()
                ->nestedRecursiveRules([new OAuthRedirectUri()]),
            CheckboxList::make('scopes')
                ->label('Allowed Scopes')
                ->helperText('The most the application may ask for. People approve what it requests, and their own permissions still apply.')
                ->options(OAuthServiceProvider::scopes()),
            Toggle::make('first_party')
                ->label('First-party application')
                ->helperText('Skip the consent screen. Only for applications your organization runs and trusts.'),
        ];
    }

    /**
     * The step that shows a registered or regenerated application's credentials, once.
     *
     * @return list<Section>
     */
    public static function credentialsStep(RevealOnceSecret $secret): array
    {
        return [
            Section::make()
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->iconColor('warning')
                ->description(new HtmlString('Give these to the application\'s developers. <strong class="text-black dark:text-white">The secret won\'t be shown again.</strong>'))
                ->schema([
                    $secret->identifierEntry('client_id', 'Client ID'),
                    $secret->secretEntry('client_secret', 'Client Secret'),
                    TextEntry::make('public_client')
                        ->hiddenLabel()
                        ->state('This is a public application: it has no secret and must use PKCE.')
                        ->visible(fn (): bool => $secret->issued() && $secret->secret() === null),
                    TextEntry::make('endpoints')
                        ->label('Endpoints')
                        ->state(new HtmlString('Authorize: <code>' . e(url('/oauth/authorize')) . '</code><br>Token: <code>' . e(url('/oauth/token')) . '</code>')),
                ]),
        ];
    }
}
