<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceClients\Actions;

use App\Domains\Auth\Actions\Api\RotateServiceClient;
use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use App\Filament\Resources\ServiceClients\Schemas\ServiceClientSchemas;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;

class RotateServiceClientAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'rotateServiceClient';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(SystemPermission::ManageApiAccess)
            ->hidden(fn (): bool => resolve('impersonate')->isImpersonating())
            ->label('Rotate')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->outlined()
            ->size(Size::ExtraSmall)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->mountUsing(ServiceClientSchemas::mountFresh(ServiceClientSchemas::SESSION_KEY_ROTATE))
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
                    ->afterValidation(function (array $state, RotateServiceClient $rotateServiceClient, OAuthClient $record): void {
                        // A rotation already happened in this wizard session; don't create a second replacement.
                        if (Session::has(ServiceClientSchemas::SESSION_KEY_ROTATE)) {
                            return;
                        }

                        /** @var User $rotatedBy */
                        $rotatedBy = auth()->user();
                        $configuration = ServiceClientSchemas::normalizeConfigurationState($state);

                        [$secret, $replacement] = $rotateServiceClient(
                            previous: $record,
                            rotatedBy: $rotatedBy,
                            name: $configuration['name'],
                            secretExpiresAt: $configuration['secret_expires_at'],
                            allowedIps: $configuration['allowed_ips'],
                        );

                        ServiceClientSchemas::storeCredentials(ServiceClientSchemas::SESSION_KEY_ROTATE, $replacement, $secret, [
                            'record_id' => $record->getKey(),
                        ]);
                    }),
                Wizard\Step::make('Copy Credentials')
                    ->schema(ServiceClientSchemas::copyCredentialsStepSchema(ServiceClientSchemas::SESSION_KEY_ROTATE)),
            ])
            ->modalSubmitAction(fn (Action $action) => ServiceClientSchemas::copyCredentialsSubmitButton($action))
            ->action(fn () => ServiceClientSchemas::clearCredentials(ServiceClientSchemas::SESSION_KEY_ROTATE))
            ->successNotificationTitle('Replacement Service Client Created')
            ->visible(fn (OAuthClient $record): bool => ServiceClientSchemas::canShowRotate($record));
    }
}
