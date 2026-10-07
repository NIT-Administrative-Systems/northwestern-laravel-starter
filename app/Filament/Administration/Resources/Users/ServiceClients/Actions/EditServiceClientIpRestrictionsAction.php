<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\ServiceClients\Actions;

use App\Domains\Api\Concerns\AuthorizesCredentials;
use App\Domains\Api\Enums\CredentialKind;
use App\Domains\Api\Enums\CredentialOperation;
use App\Domains\Auth\Actions\Api\UpdateServiceClientIpRestrictions;
use App\Domains\Auth\Models\OAuthClient;
use App\Filament\Administration\Resources\Users\RelationManagers\ServiceClientsRelationManager;
use App\Filament\Administration\Resources\Users\ServiceClients\Schemas\ServiceClientSchemas;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Northwestern\SysDev\Chassis\Rules\ValidIpOrCidrRule;

class EditServiceClientIpRestrictionsAction extends Action
{
    use AuthorizesCredentials;

    public static function getDefaultName(): ?string
    {
        return 'editServiceClientIpRestrictions';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(fn (ServiceClientsRelationManager $livewire): bool => static::allowsCredential(CredentialOperation::Modify, CredentialKind::ServiceClient, $livewire->apiUser()))
            ->label('Edit IP Restrictions')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->modalHeading('Edit IP Restrictions')
            ->modalDescription('The IP addresses or CIDR ranges this service client may call the API from. Leave the list empty to allow any address.')
            ->modalIcon(Heroicon::OutlinedShieldCheck)
            ->modalIconColor('gray')
            ->modalWidth('xl')
            ->schema([
                Section::make()
                    ->schema([
                        TagsInput::make('allowed_ips')
                            ->label('Allowed IP Addresses')
                            ->placeholder('e.g., 192.168.1.1 or 10.0.0.0/8')
                            ->helperText('One IP address or CIDR range per entry. Remove them all to allow any address.')
                            ->hintIcon(Heroicon::OutlinedInformationCircle)
                            ->hintIconTooltip(
                                'For integrations routed through an API gateway (e.g., Apigee), network filtering can typically be managed by the proxy and this field is unnecessary. Only define IPs here for direct, external integrations requiring an extra layer of application-level security.'
                            )
                            ->nestedRecursiveRules([new ValidIpOrCidrRule()])
                            ->reorderable(),
                    ]),
            ])
            ->fillForm(fn (OAuthClient $record): array => ['allowed_ips' => $record->allowed_ips])
            ->action(fn (OAuthClient $record, array $data, UpdateServiceClientIpRestrictions $updateIpRestrictions) => $updateIpRestrictions($record, $data['allowed_ips'] ?? null, static::actingUser()))
            ->successNotification(
                fn (OAuthClient $record) => Notification::make()
                    ->title('IP Restrictions Updated')
                    ->body(filled($record->allowed_ips)
                        ? 'The client is now restricted to ' . count($record->allowed_ips) . ' IP ' . Str::plural('address', count($record->allowed_ips))
                        : 'The client now accepts requests from any IP address')
                    ->success()
            )
            ->visible(fn (OAuthClient $record): bool => ServiceClientSchemas::isMutable($record));
    }
}
