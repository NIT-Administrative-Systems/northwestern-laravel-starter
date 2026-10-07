<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Actions;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\SignIn;
use App\Domains\User\Actions\Local\CreateLocalUser;
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

        $this->authorize(SystemPermission::CreateUsers)
            ->visible(fn () => resolve(SignIn::class)->offers(SignInMethod::EmailCode))
            ->label('Add Local User')
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
                            ->label('Email')
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
                            ->label('Send a verification code now')
                            ->default(false)
                            ->helperText(
                                'Leave this off if they aren\'t ready to sign in yet. They can request a code themselves at any time.'
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
                    ->title('Local User Created')
                    ->body("{$user->full_name} ({$user->email}) was added.")
                    ->send();

                return redirect()->route('filament.administration.resources.users.view', ['record' => $user]);
            });
    }
}
