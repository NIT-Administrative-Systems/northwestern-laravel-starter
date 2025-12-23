<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessTokens\Actions;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\Auth\Models\AccessToken;
use App\Filament\Resources\AccessTokens\Schemas\AccessTokenSchemas;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class EditAccessTokenIpRestrictionsAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editAccessTokenIpRestrictions';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::MANAGE_API_USERS)
            ->label('Edit IP Restrictions')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->modalHeading('Edit IP Restrictions')
            ->modalDescription('Update the list of allowed source IP addresses or CIDR ranges for this token. This provides an extra layer of security for direct integrations. Leaving the list empty will allow all IPs.')
            ->modalIcon(Heroicon::OutlinedShieldCheck)
            ->modalIconColor('gray')
            ->modalWidth('xl')
            ->schema([
                Section::make()
                    ->schema([
                        TagsInput::make('allowed_ips')
                            ->label('Allowed IP Addresses')
                            ->placeholder('e.g., 192.168.1.1 or 10.0.0.0/8')
                            ->helperText('Enter one or more IP addresses or CIDR ranges. Delete all entries to allow any IP address.')
                            ->hintIcon(Heroicon::OutlinedInformationCircle)
                            ->hintIconTooltip(
                                'For integrations routed through an API gateway (e.g., Apigee), network filtering can typically be managed by the proxy and this field is unnecessary. Only define IPs here for direct, external integrations requiring an extra layer of application-level security.'
                            )
                            ->reorderable(),
                    ]),
            ])
            ->fillForm(fn (AccessToken $record) => [
                'allowed_ips' => $record->allowed_ips,
            ])
            ->action(function (AccessToken $record, array $data) {
                $record->update([
                    'allowed_ips' => filled($data['allowed_ips']) ? $data['allowed_ips'] : null,
                ]);
            })
            ->successNotificationTitle('IP restrictions updated')
            ->successNotification(
                fn (AccessToken $record) => \Filament\Notifications\Notification::make()
                    ->title('IP restrictions updated')
                    ->body(filled($record->allowed_ips)
                        ? 'Token is now restricted to ' . count($record->allowed_ips) . ' IP ' . Str::plural('address', count($record->allowed_ips))
                        : 'Token now accepts requests from any IP address')
                    ->success()
            )
            ->visible(fn (AccessToken $record) => AccessTokenSchemas::isMutable($record));
    }
}
