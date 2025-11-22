<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Filament\Resources\Users\UserResource;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class CreateNorthwesternUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createNorthwesternUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(PermissionEnum::CREATE_USERS)
            ->label('Create NU User')
            ->name('create-nu-user')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('primary')
            ->modalHeading('Create NU User')
            ->modalDescription('Enter a NetID or email to look up and create a user from the Northwestern Directory.')
            ->modalWidth('md')
            ->schema([
                TextInput::make('netid')
                    ->label('NetID or Email')
                    ->placeholder('e.g., abc123 or user@northwestern.edu')
                    ->required()
                    ->maxLength(255)
                    ->autocomplete(false)
                    ->afterStateUpdated(function ($state, $set) {
                        $set('netid', trim($state ?? ''));
                    })
                    ->rules([
                        fn (FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory) => function ($attribute, $value, $fail) use ($findOrUpdateUserFromDirectory) {
                            $searchValue = trim($value);

                            if (blank($searchValue)) {
                                return;
                            }

                            try {
                                $user = $findOrUpdateUserFromDirectory($searchValue, immediate: true);

                                if (! $user) {
                                    $fail('Not found in the directory. You can search by a Northwestern email address or NetID.');
                                }
                            } catch (BadDirectoryEntry) {
                                $fail('This user has an incomplete entry in the Northwestern Directory. They cannot be loaded.');
                            } catch (Exception $e) {
                                report($e);
                            }
                        },
                    ]),
            ])
            ->action(function (array $data, FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory) {
                $searchValue = trim((string) $data['netid']);

                try {
                    $user = ($findOrUpdateUserFromDirectory)($searchValue, immediate: true);

                    if (! $user) {
                        // This shouldn't happen since validation passed, but handle it
                        Notification::make()
                            ->title('User not found')
                            ->body('Unable to locate the user in the Northwestern Directory. Please verify the information and try again.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $wasJustCreated = $user->created_at->gt(now()->subSeconds(30));

                    if ($wasJustCreated) {
                        Notification::make()
                            ->title('User created')
                            ->body("{$user->full_name} has been added to the system.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('User found')
                            ->body("{$user->full_name} already exists in the system.")
                            ->success()
                            ->send();
                    }

                    return redirect()->to(UserResource::getUrl('view', ['record' => $user]));
                } catch (Exception $e) {
                    Notification::make()
                        ->title('User creation failed')
                        ->body('An unexpected error occurred. Please try again or contact support if the issue persists.')
                        ->danger()
                        ->send();

                    report($e);

                    return;
                }
            });
    }
}
