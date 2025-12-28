<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\Auth\Enums\PermissionEnum;
use App\Domains\User\Actions\Api\CreateApiUser;
use App\Domains\User\Models\User;
use App\Filament\Resources\AccessTokens\Schemas\AccessTokenSchemas;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Support\Icons\Heroicon;

class CreateApiUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createApiUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::MANAGE_API_USERS)
            ->visible(config('auth.api.enabled'))
            ->label('Create API User')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->steps([
                Wizard\Step::make('User Information')
                    ->schema([
                        Section::make()
                            ->schema([
                                Grid::make()
                                    ->columns()
                                    ->schema([
                                        TextInput::make('first_name')
                                            ->label('Label')
                                            ->placeholder('e.g., McC Reporting Tool')
                                            ->helperText('Human-readable name for this API user. Will be suffixed with "API" for display purposes.')
                                            ->required()
                                            ->maxLength(255)
                                            ->autocomplete(false),

                                        TextInput::make('username')
                                            ->label('Username')
                                            ->prefix('api-')
                                            ->placeholder('e.g., mcc-reporting')
                                            ->helperText('Technical identifier using lowercase letters and hyphens only')
                                            ->required()
                                            ->maxLength(255)
                                            ->autocomplete(false)
                                            ->regex('/^[a-z-]+$/')
                                            ->rules([
                                                function () {
                                                    return function (string $attribute, $value, $fail) {
                                                        // Skip validation if we already created a user in this session
                                                        if (session()->has(AccessTokenSchemas::SESSION_KEY . '.user_id')) {
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
                                                'regex' => 'Username must only contain lowercase letters and hyphens.',
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
                                    ->placeholder('e.g., Access to student data for McCormick reporting purposes...')
                                    ->helperText('Optional. Describe the purpose and usage of this API integration for internal reference.')
                                    ->rows(3)
                                    ->maxLength(1000)
                                    ->columnSpanFull(),

                                TextInput::make('email')
                                    ->label('Contact Email')
                                    ->email()
                                    ->placeholder('team@northwestern.edu')
                                    ->helperText('Optional. When provided, automated notifications will be sent prior to token expiration. Leave blank if no notifications are needed.')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Wizard\Step::make('Configure Token')
                    ->schema([
                        AccessTokenSchemas::tokenConfigurationSection(),
                    ])
                    ->afterValidation(function ($state, $set, CreateApiUser $createApiUser) {
                        $username = 'api-' . ltrim(strtolower((string) $state['username']), 'api-');

                        $configuration = AccessTokenSchemas::normalizeConfigurationState($state);

                        [$user, $token] = $createApiUser(
                            username: $username,
                            firstName: $state['first_name'],
                            tokenName: $configuration['name'],
                            description: $state['description'] ?? null,
                            email: $state['email'] ?? null,
                            expiresAt: $configuration['expires_at'],
                            allowedIps: $configuration['allowed_ips'],
                        );

                        session([
                            AccessTokenSchemas::SESSION_KEY => [
                                'token' => $token,
                                'user_id' => $user->getKey(),
                            ],
                        ]);
                    }),

                Wizard\Step::make('Copy Token')
                    ->schema(
                        AccessTokenSchemas::copyTokenStepSchema(),
                    ),
            ])
            ->modalSubmitAction(fn (Action $action) => AccessTokenSchemas::copyTokenSubmitButton($action))
            ->action(function () {
                $userId = session(AccessTokenSchemas::SESSION_KEY . '.user_id');
                AccessTokenSchemas::clearTokenSession();

                if ($userId) {
                    /** @var User $user */
                    $user = UserResource::getModel()::find($userId);
                    if ($user) {
                        Notification::make()
                            ->title('API user created')
                            ->body("{$user->full_name} has been created with an active access token.")
                            ->success()
                            ->send();

                        return redirect()->to(UserResource::getUrl('view', ['record' => $user]));
                    }
                }
            });
    }
}
