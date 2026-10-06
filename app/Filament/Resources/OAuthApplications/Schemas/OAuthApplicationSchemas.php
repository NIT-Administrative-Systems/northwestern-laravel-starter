<?php

declare(strict_types=1);

namespace App\Filament\Resources\OAuthApplications\Schemas;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\Auth\Rules\OAuthRedirectUri;
use App\Providers\OAuthServiceProvider;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use Phiki\Grammar\Grammar;

/**
 * Form fields and the one-time credentials step for registering OAuth applications.
 */
class OAuthApplicationSchemas
{
    /** Stored value: `['client_id' => string, 'secret' => string|null (encrypted)]` */
    public const string SESSION_KEY = 'oauth_application_credentials';

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

    public static function storeCredentials(OAuthClient $client, ?string $secret): void
    {
        Session::put(self::SESSION_KEY, [
            'client_id' => $client->getKey(),
            'secret' => $secret === null ? null : Crypt::encryptString($secret),
        ]);
    }

    /**
     * @return list<Section>
     */
    public static function credentialsStep(): array
    {
        $stored = fn (string $key): ?string => (($value = Session::get(self::SESSION_KEY)[$key] ?? null) !== null && $key === 'secret')
            ? Crypt::decryptString($value)
            : $value;

        return [
            Section::make()
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->iconColor('warning')
                ->description(new HtmlString('Give these to the application\'s developers. <strong class="text-black dark:text-white">The secret won\'t be shown again.</strong>'))
                ->schema([
                    CodeEntry::make('client_id')
                        ->label('Client ID')
                        ->grammar(Grammar::Txt)
                        ->state(fn () => $stored('client_id'))
                        ->dehydrated(false)
                        ->copyable(),
                    CodeEntry::make('client_secret')
                        ->label('Client Secret')
                        ->grammar(Grammar::Txt)
                        ->state(fn () => $stored('secret'))
                        ->visible(fn (): bool => filled(Session::get(self::SESSION_KEY)['secret'] ?? null))
                        ->dehydrated(false)
                        ->copyable(),
                    TextEntry::make('public_client')
                        ->hiddenLabel()
                        ->state('This is a public application: it has no secret and must use PKCE.')
                        ->visible(fn (): bool => blank(Session::get(self::SESSION_KEY)['secret'] ?? null)),
                    TextEntry::make('endpoints')
                        ->label('Endpoints')
                        ->state(new HtmlString('Authorize: <code>' . e(url('/oauth/authorize')) . '</code><br>Token: <code>' . e(url('/oauth/token')) . '</code>')),
                ]),
        ];
    }

    public static function clearCredentials(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
