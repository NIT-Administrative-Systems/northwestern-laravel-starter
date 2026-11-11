<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Roles\Actions;

use App\Domains\Access\Actions\AddPeopleToRole;
use App\Domains\Access\Actions\AddPersonToRole;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Roles\RelationManagers\UsersRelationManager;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Number;
use Northwestern\SysDev\Chassis\Formatting\CountInWords;

/**
 * Adds a pasted list of NetIDs and email addresses to a role, on a role's Assigned Users tab
 * (that role) or the Users list (a role chosen here). The work is queued; see
 * {@see AddPeopleToRole}.
 */
class AddPeopleAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'addPeople';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorize(fn (mixed $livewire): bool|Response => self::authorizeFor($livewire))
            ->label('Paste NetIDs or Emails')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->modalHeading('Add People')
            ->modalDescription('Each NetID or Northwestern email address is looked up in the Northwestern Directory. Any other email address that matches no one gets a local account. You\'ll get a notification when they\'ve been added.')
            ->modalSubmitActionLabel('Add People')
            ->schema([
                Select::make('role_id')
                    ->label('Role')
                    ->options(fn (): array => resolve(AddPersonToRole::class)->rolesFor(self::actor()))
                    ->searchable()
                    ->required()
                    ->hidden(fn (mixed $livewire): bool => $livewire instanceof UsersRelationManager),
                Textarea::make('people')
                    ->label('NetIDs or Email Addresses')
                    ->helperText('Separate them with commas, spaces or line breaks, up to ' . Number::format(AddPeopleToRole::MAX_PEOPLE) . ' at a time.')
                    ->rows(8)
                    ->required()
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (count(AddPeopleToRole::parse((string) $value)) > AddPeopleToRole::MAX_PEOPLE) {
                            $fail('Add up to ' . Number::format(AddPeopleToRole::MAX_PEOPLE) . ' people at a time.');
                        }
                    }),
            ])
            ->action(function (array $data, mixed $livewire, AddPeopleToRole $addPeopleToRole): void {
                $role = self::roleOf($livewire) ?? Role::query()->findOrFail($data['role_id']);

                $import = $addPeopleToRole($role, $data['people'], self::actor());

                Notification::make()
                    ->success()
                    ->title('Adding People')
                    ->body('Adding ' . CountInWords::of($import->total_rows, 'person') . " to the {$role->name} role. You'll get a notification when it's done.")
                    ->send();
            });
    }

    /**
     * On a role's tab, whether people may be added to that role; on the Users list, whether
     * there's any role they may be added to.
     */
    public static function authorizeFor(mixed $livewire): bool|Response
    {
        $role = self::roleOf($livewire);

        return $role instanceof Role
            ? resolve(AddPersonToRole::class)->decide($role, self::actor())
            : resolve(AddPersonToRole::class)->rolesFor(self::actor()) !== [];
    }

    public static function roleOf(mixed $livewire): ?Role
    {
        if (! $livewire instanceof UsersRelationManager) {
            return null;
        }

        /** @var Role */
        return $livewire->getOwnerRecord();
    }

    private static function actor(): User
    {
        /** @var User */
        return Filament::auth()->user();
    }
}
