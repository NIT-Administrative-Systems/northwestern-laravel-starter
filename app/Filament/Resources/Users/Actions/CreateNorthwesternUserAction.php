<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Enums\DirectorySearchType;
use App\Domains\User\Models\User;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Northwestern\SysDev\SOA\DirectorySearch;
use Throwable;

class CreateNorthwesternUserAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createNorthwesternUser';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(SystemPermission::CreateUsers)
            ->label('Add NU User')
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
                        fn (DirectorySearch $directorySearch) => function ($attribute, $value, $fail) use ($directorySearch) {
                            $searchValue = trim($value);

                            if (blank($searchValue)) {
                                return;
                            }

                            try {
                                $searchType = DirectorySearchType::fromSearchValue($searchValue);
                                $result = $directorySearch->lookup($searchValue, $searchType->value, 'basic');

                                if (! $result) {
                                    $fail('Not found in the directory. You can search by a Northwestern email address or NetID.');
                                }
                            } catch (Throwable $e) {
                                report($e);
                                $fail('Unable to search the directory. Please try again.');
                            }
                        },
                    ]),
            ])
            ->action(function (array $data, FindOrUpdateUserFromDirectory $findOrUpdateUserFromDirectory) {
                $searchValue = trim((string) $data['netid']);

                try {
                    $user = ($findOrUpdateUserFromDirectory)($searchValue, immediate: true);

                    if (! $user instanceof User) {
                        // This shouldn't happen since validation passed, but handle it
                        Notification::make()
                            ->title('User not found')
                            ->body('Unable to locate the user in the Northwestern Directory. Please verify the information and try again.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $wasJustCreated = $user->created_at?->gt(now()->subSeconds(30)) ?? false;

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
                } catch (Throwable $e) {
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
