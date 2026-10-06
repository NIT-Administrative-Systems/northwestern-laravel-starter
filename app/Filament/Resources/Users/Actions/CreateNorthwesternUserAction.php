<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Actions;

use App\Domains\Auth\Enums\SystemPermission;
use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Enums\DirectorySearchType;
use App\Domains\User\Models\User;
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
            ->label('Add Northwestern User')
            ->name('create-nu-user')
            ->icon(Heroicon::OutlinedIdentification)
            ->color('primary')
            ->modalHeading('Add Northwestern User')
            ->modalDescription('Look someone up in the Northwestern Directory by NetID or email.')
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

                            // Optional locally: say so instead of reporting a failed API call.
                            if (blank(config('nusoa.directorySearch.apiKey'))) {
                                $fail('Directory Search isn\'t configured. Set DIRECTORY_SEARCH_API_KEY to look people up.');

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
                                $fail('We couldn\'t search the directory. Try again.');
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
                            ->title('User Not Found')
                            ->body('No one with that NetID or email is in the Northwestern Directory.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $wasJustCreated = $user->created_at?->gt(now()->subSeconds(30)) ?? false;

                    if ($wasJustCreated) {
                        Notification::make()
                            ->title('User Created')
                            ->body("{$user->full_name} was added.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('User Found')
                            ->body("{$user->full_name} already has an account.")
                            ->success()
                            ->send();
                    }

                    return redirect()->route('filament.administration.resources.users.view', ['record' => $user]);
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('User Creation Failed')
                        ->body('Something went wrong. Try again, and contact support if it keeps happening.')
                        ->danger()
                        ->send();

                    report($e);

                    return;
                }
            });
    }
}
