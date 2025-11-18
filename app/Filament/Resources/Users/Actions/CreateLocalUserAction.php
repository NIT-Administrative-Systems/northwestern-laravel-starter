<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\User\Actions\CreateLocalUser;
use App\Domains\User\Enums\PermissionEnum;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

class CreateLocalUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createLocalUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::CREATE_USERS)
            ->visible(fn () => config('auth.local.enabled'))
            ->label('Create Local User')
            ->icon(Heroicon::OutlinedUserPlus)
            ->outlined()
            ->schema([
                Section::make('Details')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->autocomplete(false)
                            ->unique('users', 'email')
                            ->helperText('The email address used to sign in.')
                            ->columnSpanFull(),

                        TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->autocomplete(false),

                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->autocomplete(false),

                        TextInput::make('title')
                            ->label('Job Title')
                            ->required()
                            ->autocomplete(false)
                            ->helperText('The user’s role or position.'),

                        TextInput::make('department')
                            ->label('Organization')
                            ->required()
                            ->autocomplete(false)
                            ->helperText('The partner organization this user represents.'),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->autocomplete(false)
                            ->helperText('Optional internal notes about this user.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Access')
                    ->columns(1)
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->schema([
                        Checkbox::make('send_login_link')
                            ->label('Send sign-in link')
                            ->default(false)
                            ->helperText(
                                'Select this when the user is ready to access the application immediately. If left unchecked, they can request a sign-in link on their own at any time.'
                            ),
                    ]),
            ])
            ->action(function (array $data) {
                $createLocalUser = resolve(CreateLocalUser::class);

                $user = ($createLocalUser)(
                    email: $data['email'],
                    firstName: $data['first_name'],
                    lastName: $data['last_name'],
                    title: $data['title'],
                    department: $data['department'],
                    description: $data['description'] ?? null,
                    sendLoginLink: $data['send_login_link'] ?? false,
                );

                Notification::make()
                    ->success()
                    ->title('Local user created')
                    ->body("User {$user->full_name} ({$user->email}) has been created.")
                    ->send();

                return redirect()->to(UserResource::getUrl('view', ['record' => $user]));
            });
    }
}
