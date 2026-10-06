<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Actions\Api\CreateApiUser;
use App\Domains\User\Models\User;
use App\Filament\Resources\ServiceClients\Schemas\ServiceClientSchemas;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Session;

class CreateApiUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createApiUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(SystemPermission::ManageApiAccess)
            ->visible((bool) config('api.enabled'))
            ->label('Add API User')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->steps([
                Wizard\Step::make('Details')
                    ->schema([
                        Section::make()
                            ->schema([
                                Grid::make()
                                    ->columns()
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('Label')
                                            ->placeholder('e.g., McCormick Reporting Tool')
                                            ->helperText('Shown with "API" after it, such as "McCormick Reporting Tool API".')
                                            ->required()
                                            ->maxLength(255)
                                            ->autocomplete(false),

                                        TextInput::make('username')
                                            ->label('Username')
                                            ->prefix('api-')
                                            ->placeholder('e.g., mcc-reporting')
                                            ->helperText('Lowercase letters and hyphens only.')
                                            ->required()
                                            ->maxLength(255)
                                            ->autocomplete(false)
                                            ->regex('/^[a-z-]+$/')
                                            ->rules([
                                                function () {
                                                    return function (string $attribute, $value, $fail) {
                                                        // Skip validation if we already created a user in this session
                                                        if (Session::has(ServiceClientSchemas::SESSION_KEY_CREATE_API_USER)) {
                                                            return;
                                                        }

                                                        $prefixedUsername = sprintf(
                                                            'api-%s',
                                                            preg_replace('/^api-/', '', trim($value))
                                                        );

                                                        if (User::where('username', $prefixedUsername)->exists()) {
                                                            $fail('This username is already taken.');
                                                        }
                                                    };
                                                },
                                            ])
                                            ->validationMessages([
                                                'regex' => 'Use only lowercase letters and hyphens.',
                                            ])
                                            ->afterStateUpdated(function ($state, $set) {
                                                if (blank($state)) {
                                                    return;
                                                }

                                                $cleaned = preg_replace('/^api-/', '', strtolower(trim($state)));

                                                $set('username', $cleaned);
                                            }),
                                    ]),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->placeholder('e.g., Student data for McCormick reporting…')
                                    ->helperText('Optional. What the integration is for, for administrators\' reference.')
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),

                                TextInput::make('email')
                                    ->label('Contact Email')
                                    ->email()
                                    ->placeholder('team@northwestern.edu')
                                    ->helperText('Optional. Gets an email before a service client\'s secret expires.')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Wizard\Step::make('Configure')
                    ->schema([
                        ServiceClientSchemas::clientConfigurationSection(),
                    ])
                    ->afterValidation(function (array $state, CreateApiUser $createApiUser): void {
                        if (Session::has(ServiceClientSchemas::SESSION_KEY_CREATE_API_USER)) {
                            return;
                        }

                        $username = 'api-' . preg_replace('/^api-/', '', strtolower((string) $state['username']));

                        $configuration = ServiceClientSchemas::normalizeConfigurationState($state);

                        [$user, $secret, $client] = $createApiUser(
                            username: $username,
                            firstName: $state['first_name'],
                            clientName: $configuration['name'],
                            secretExpiresAt: $configuration['secret_expires_at'],
                            description: $state['description'] ?? null,
                            email: $state['email'] ?? null,
                            allowedIps: $configuration['allowed_ips'],
                        );

                        ServiceClientSchemas::storeCredentials(ServiceClientSchemas::SESSION_KEY_CREATE_API_USER, $client, $secret, [
                            'user_id' => $user->getKey(),
                        ]);
                    }),

                Wizard\Step::make('Copy Credentials')
                    ->schema(
                        ServiceClientSchemas::copyCredentialsStepSchema(ServiceClientSchemas::SESSION_KEY_CREATE_API_USER),
                    ),
            ])
            ->modalSubmitAction(fn (Action $action) => ServiceClientSchemas::copyCredentialsSubmitButton($action))
            ->action(function () {
                $userId = session(ServiceClientSchemas::SESSION_KEY_CREATE_API_USER . '.user_id');
                ServiceClientSchemas::clearCredentials(ServiceClientSchemas::SESSION_KEY_CREATE_API_USER);

                if ($userId) {
                    /** @var User $user */
                    $user = User::query()->find($userId);
                    if ($user) {
                        Notification::make()
                            ->title('API User Created')
                            ->body("{$user->full_name} is ready, with a service client.")
                            ->success()
                            ->send();

                        return redirect()->route('filament.administration.resources.users.view', ['record' => $user]);
                    }
                }
            });
    }
}
