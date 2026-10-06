<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceClients\Schemas;

use App\Domains\Auth\Enums\CredentialStatus;
use App\Domains\Auth\Enums\TokenExpiration;
use App\Domains\Auth\Models\OAuthClient;
use Carbon\CarbonInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use Northwestern\SysDev\Chassis\Rules\ValidIpOrCidrRule;
use Phiki\Grammar\Grammar;

/**
 * Reusable schema fragments and helpers for the service client {@see Wizard}s.
 *
 * A new client's secret is shown once. Between the wizard's steps it is kept in the session,
 * encrypted, and cleared when the operator confirms they have copied it.
 */
class ServiceClientSchemas
{
    /**
     * Session key for the "Create Client" wizard flow.
     *
     * Stored value: `['client_id' => string, 'secret' => string (encrypted)]`
     */
    public const string SESSION_KEY_CREATE = 'service_client_credentials:create';

    /**
     * Session key for the "Create API User" wizard flow.
     *
     * Stored value: `['client_id' => string, 'secret' => string (encrypted), 'user_id' => int]`
     */
    public const string SESSION_KEY_CREATE_API_USER = 'service_client_credentials:create_api_user';

    /**
     * Session key for the "Rotate Client" wizard flow.
     *
     * Stored value: `['client_id' => string, 'secret' => string (encrypted), 'record_id' => string]`
     */
    public const string SESSION_KEY_ROTATE = 'service_client_credentials:rotate';

    /**
     * The client's name, secret lifetime and IP restrictions. In rotation flows the previous
     * client is bound and its values pre-fill the form.
     */
    public static function clientConfigurationSection(): Section
    {
        return Section::make()
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->default(fn ($record) => $record instanceof OAuthClient ? $record->name : null)
                    ->placeholder('e.g., Apigee Production')
                    ->required()
                    ->maxLength(255),

                Select::make('expiration')
                    ->label('Secret Expires')
                    ->options(TokenExpiration::class)
                    ->required()
                    ->live()
                    ->helperText(function ($state) {
                        $note = 'A secret can\'t be extended. Rotate the client to issue a new one.';

                        $expiration = $state instanceof TokenExpiration ? $state : TokenExpiration::tryFrom((int) $state);

                        if (! $expiration instanceof TokenExpiration) {
                            return $note;
                        }

                        $formattedDate = $expiration->expiresAt()->format('F j, Y');

                        return new HtmlString("The secret will expire on <strong class=\"text-black dark:text-white\">{$formattedDate}</strong>.<br>{$note}");
                    }),

                TagsInput::make('allowed_ips')
                    ->label('Allowed IP Addresses')
                    ->default(fn ($record) => $record instanceof OAuthClient ? ($record->allowed_ips ?? []) : null)
                    ->placeholder('e.g., 192.168.1.1 or 10.0.0.0/8')
                    ->helperText('Leave empty to allow any address. Use CIDR notation for ranges, such as 10.0.0.0/8.')
                    ->hintIcon(Heroicon::OutlinedInformationCircle)
                    ->hintIconTooltip(
                        'For integrations routed through an API gateway (e.g., Apigee), network filtering can typically be managed by the proxy and this field is unnecessary. Only define IPs here for direct, external integrations requiring an extra layer of application-level security.'
                    )
                    ->nestedRecursiveRules([new ValidIpOrCidrRule()])
                    ->columnSpanFull()
                    ->reorderable(),
            ]);
    }

    /**
     * @param  array{name: string, expiration: TokenExpiration|int|string, allowed_ips?: array<int,string>|null}  $state
     * @return array{name: non-empty-string, secret_expires_at: CarbonInterface, allowed_ips: list<non-empty-string>|null}
     */
    public static function normalizeConfigurationState(array $state): array
    {
        $expiration = $state['expiration'] instanceof TokenExpiration
            ? $state['expiration']
            : TokenExpiration::from((int) $state['expiration']);

        /** @var list<non-empty-string>|null $allowedIps */
        $allowedIps = filled($state['allowed_ips'] ?? null) ? array_values($state['allowed_ips']) : null;

        /** @var non-empty-string $name */
        $name = $state['name'];

        return [
            'name' => $name,
            'secret_expires_at' => $expiration->expiresAt(),
            'allowed_ips' => $allowedIps,
        ];
    }

    /**
     * Keep a new client's credentials for the wizard's copy step.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function storeCredentials(string $sessionKey, OAuthClient $client, string $secret, array $extra = []): void
    {
        Session::put($sessionKey, [
            'client_id' => $client->getKey(),
            'secret' => Crypt::encryptString($secret),
            ...$extra,
        ]);
    }

    /**
     * The "Copy Credentials" step: the client ID and secret, and how to use them. In the
     * rotation flow, the credentials only show for the client being rotated.
     *
     * @return array<int, Section>
     */
    public static function copyCredentialsStepSchema(string $sessionKey): array
    {
        $credential = function (string $key) use ($sessionKey) {
            return function ($record) use ($key, $sessionKey): ?string {
                $stored = session($sessionKey);

                if (! is_array($stored)) {
                    return null;
                }

                if (isset($stored['record_id']) && $record instanceof OAuthClient && $record->getKey() !== $stored['record_id']) {
                    return null;
                }

                $value = $stored[$key] ?? null;

                if (! is_string($value)) {
                    return null;
                }

                return $key === 'secret' ? Crypt::decryptString($value) : $value;
            };
        };

        return [
            Section::make()
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->iconColor('warning')
                ->iconSize(IconSize::Large)
                ->description(new HtmlString('Copy the client ID and secret and store them somewhere safe.<br><strong class="text-black dark:text-white">The secret won\'t be shown again.</strong>'))
                ->schema([
                    CodeEntry::make('client_id')
                        ->label('Client ID')
                        ->grammar(Grammar::Txt)
                        ->state($credential('client_id'))
                        ->dehydrated(false)
                        ->copyable(),
                    CodeEntry::make('client_secret')
                        ->label('Client Secret')
                        ->grammar(Grammar::Txt)
                        ->state($credential('secret'))
                        ->dehydrated(false)
                        ->copyable(),
                ]),
            Section::make('Usage')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->iconColor('info')
                ->iconSize(IconSize::Large)
                ->schema([
                    ViewEntry::make('usage_info')
                        ->hiddenLabel()
                        ->view('filament.resources.users.entries.api-authentication-overview')
                        ->dehydrated(false),
                ]),
        ];
    }

    public static function copyCredentialsSubmitButton(Action $action): Action
    {
        return $action
            ->label('I\'ve copied the secret')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->iconPosition(IconPosition::After)
            ->color('success')
            ->outlined();
    }

    /**
     * Whether a client can still be changed: rotated, restricted or revoked.
     */
    public static function isMutable(OAuthClient $client): bool
    {
        return $client->status === CredentialStatus::Active;
    }

    /**
     * Show "Rotate" for an active client, and for the client whose rotation is in progress, so
     * the wizard can finish even if the client changed state meanwhile.
     */
    public static function canShowRotate(OAuthClient $client): bool
    {
        if (self::isMutable($client)) {
            return true;
        }

        $rotation = session(self::SESSION_KEY_ROTATE);

        return is_array($rotation) && ($rotation['record_id'] ?? null) === $client->getKey();
    }

    /**
     * Forget a new client's secret once the operator has copied it or abandoned the wizard.
     */
    public static function clearCredentials(string $sessionKey): void
    {
        Session::forget($sessionKey);
    }

    /**
     * Mount a credentials wizard with nothing left from an earlier run.
     *
     * Only the final submit clears the session key, so a run that was cancelled or closed
     * leaves its secret behind. Without this, the next run of the wizard, on any record,
     * would skip its work and show that secret.
     */
    public static function mountFresh(string $sessionKey): Closure
    {
        return function (?Schema $schema) use ($sessionKey): void {
            self::clearCredentials($sessionKey);
            $schema?->fill();
        };
    }
}
