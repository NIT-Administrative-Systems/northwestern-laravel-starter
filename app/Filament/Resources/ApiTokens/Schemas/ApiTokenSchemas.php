<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiTokens\Schemas;

use App\Domains\User\Enums\ApiTokenStatusEnum;
use App\Domains\User\Models\ApiToken;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TagsInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Phiki\Grammar\Grammar;

/**
 * Reusable schema fragments and helpers for API token-related Filament {@see Wizard}s.
 */
class ApiTokenSchemas
{
    /**
     * Session key used by all flows that temporarily expose a raw API token
     *
     * The value stored under this key is a small associative array containing:
     * - **token:** `string` - The raw Bearer token
     * - **record_id:** `int|null` - The associated ApiToken ID, when applicable
     * - **user_id:** `int|null` The associated User ID, when applicable
     */
    public const string SESSION_KEY = 'api_token_credentials';

    /**
     * Generic configuration section for token validity and IP restrictions.
     *
     * Usage:
     * - In create flows, no record is bound, so the defaults assume a fresh token.
     * - In rotation flows, an {@see ApiToken} record is bound and values are
     *   pre-filled from that record where appropriate.
     */
    public static function tokenConfigurationSection(): Section
    {
        return Section::make()
            ->columns()
            ->schema([
                DatePicker::make('valid_from')
                    ->label('Valid From')
                    ->required()
                    ->native(false)
                    ->default(fn () => now()->timezone(auth()->user()->timezone)->startOfDay())
                    ->minDate(fn () => now()->timezone(auth()->user()->timezone)->startOfDay()),

                DatePicker::make('valid_to')
                    ->label('Valid To')
                    ->placeholder('Indefinite')
                    ->native(false)
                    ->default(function ($record) {
                        if ($record instanceof ApiToken && $record->valid_to) {
                            return $record->valid_to->timezone(auth()->user()->timezone);
                        }

                        return null;
                    })
                    ->minDate(fn (callable $get) => $get('valid_from')),

                TagsInput::make('allowed_ips')
                    ->label('Allowed IP Addresses')
                    ->default(function ($record) {
                        if ($record instanceof ApiToken) {
                            return $record->allowed_ips ?? [];
                        }

                        return null;
                    })
                    ->placeholder('e.g., 192.168.1.1 or 10.0.0.0/8')
                    ->helperText('Optional. Restrict this token to specific IP addresses or CIDR ranges. Leave empty to allow all IPs.')
                    ->hintIcon(Heroicon::OutlinedInformationCircle)
                    ->hintIconTooltip(
                        'For integrations routed through an API gateway (e.g., Apigee), network filtering can typically be managed by the proxy and this field is unnecessary. Only define IPs here for direct, external integrations requiring an extra layer of application-level security.'
                    )
                    ->columnSpanFull()
                    ->reorderable(),
            ]);
    }

    /**
     * Normalize token validity and IP restriction values from a {@see Wizard} step state.
     *
     * This helper converts raw form values into concrete {@see CarbonInterface} instances
     * in the authenticated user's timezone and normalizes IP data to a consistent format.
     *
     * @param  array{
     *     valid_from: mixed,
     *     valid_to?: mixed|null,
     *     allowed_ips?: array<int,string>|null
     * }  $state
     * @return array{
     *     valid_from: CarbonInterface,
     *     valid_to: CarbonInterface|null,
     *     allowed_ips: array<int,string>|null
     * }
     */
    public static function normalizeConfigurationState(array $state): array
    {
        $userTimezone = auth()->user()->timezone;

        $validFrom = Carbon::parse($state['valid_from'], $userTimezone)->startOfDay();

        $validTo = filled($state['valid_to'] ?? null)
            ? Carbon::parse($state['valid_to'], $userTimezone)->endOfDay()
            : null;

        /** @var array<int,string>|null $allowedIps */
        $allowedIps = filled($state['allowed_ips'] ?? null)
            ? $state['allowed_ips']
            : null;

        return [
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'allowed_ips' => $allowedIps,
        ];
    }

    /**
     * Schema for the "Copy Token" wizard step, including both:
     * - the raw token display; and
     * - the usage/help content.
     *
     * The token is resolved from {@see self::SESSION_KEY}. If a `record_id` is
     * present, and the current record is an {@see ApiToken}, the token is only
     * shown when the IDs match.
     *
     * @return array<int,Section>
     */
    public static function copyTokenStepSchema(): array
    {
        $copySection = Section::make()
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->iconColor('warning')
            ->iconSize(IconSize::Large)
            ->description('The Bearer token will not be shown again. Make sure to copy it now.')
            ->schema([
                CodeEntry::make('token')
                    ->label('Bearer Token')
                    ->grammar(Grammar::Txt)
                    ->state(function ($record) {
                        $data = session(self::SESSION_KEY);

                        $token = data_get($data, 'token');
                        if (! is_string($token)) {
                            return null;
                        }

                        $recordId = data_get($data, 'record_id');

                        // If this session token is tied to a specific ApiToken record,
                        // only show it when the bound record matches.
                        if ($recordId !== null && $record instanceof ApiToken) {
                            return $record->getKey() === $recordId
                                ? $token
                                : null;
                        }

                        // Create flow
                        return $token;
                    })
                    ->dehydrated(false)
                    ->copyable(),
            ]);

        $usageSection = Section::make('Usage')
            ->icon(Heroicon::OutlinedInformationCircle)
            ->iconColor('info')
            ->iconSize(IconSize::Large)
            ->schema([
                ViewEntry::make('usage_info')
                    ->hiddenLabel()
                    ->view('filament.resources.users.entries.api-authentication-overview')
                    ->dehydrated(false),
            ]);

        return [
            $copySection,
            $usageSection,
        ];
    }

    /**
     * Apply consistent styling to the "I have copied the token" submit button.
     */
    public static function copyTokenSubmitButton(Action $action): Action
    {
        return $action
            ->label('I have copied the token')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->iconPosition(IconPosition::After)
            ->color('success')
            ->outlined();
    }

    /**
     * Determine whether an API token is still mutable (e.g. may be rotated or revoked).
     *
     * A token is considered mutable while it is in either the PENDING or ACTIVE state.
     */
    public static function isMutable(ApiToken $token): bool
    {
        return in_array(
            $token->status,
            [ApiTokenStatusEnum::PENDING, ApiTokenStatusEnum::ACTIVE],
            true,
        );
    }

    /**
     * Determine whether the "Rotate" action should be visible for a given token.
     *
     * Rules:
     * - Show for any mutable token; OR
     * - Show for the token currently associated with the in-progress rotation
     *   session (so the wizard can render and close cleanly even if the token
     *   has just transitioned state).
     */
    public static function canShowRotate(ApiToken $token): bool
    {
        if (self::isMutable($token)) {
            return true;
        }

        $data = session(self::SESSION_KEY);

        return is_array($data)
            && ($data['record_id'] ?? null) === $token->getKey();
    }

    /**
     * Clear any stored raw token credentials from the session.
     *
     * This should be called once the operator has confirmed that the token
     * has been copied or when abandoning a token-related wizard.
     */
    public static function clearTokenSession(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
