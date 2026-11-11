<?php

declare(strict_types=1);

namespace App\Filament\Administration\Imports;

use App\Domains\Access\Actions\AddPeopleToRole;
use App\Domains\Access\Actions\AddPersonToRole;
use App\Domains\Access\Exceptions\PersonNotAdded;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Roles\RelationManagers\UsersRelationManager;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Number;

/**
 * Gives each person in an uploaded CSV, or a pasted list ({@see AddPeopleToRole}), the role in
 * the import's `role_id` option, through {@see AddPersonToRole}. Someone it doesn't add becomes a
 * failed row with the reason, which the person who imported them can download.
 */
class AddPeopleToRoleImporter extends Importer
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('person')
                ->label('NetID or Email')
                ->guess(['netid', 'net_id', 'email', 'email_address', 'username', 'person'])
                ->requiredMapping()
                ->example('abc123'),
            ImportColumn::make('first_name')
                ->label('First Name')
                ->guess(['first_name', 'first', 'given_name'])
                ->helperText('Used for new local accounts.')
                ->example('Willie'),
            ImportColumn::make('last_name')
                ->label('Last Name')
                ->guess(['last_name', 'last', 'surname', 'family_name'])
                ->helperText('Used for new local accounts.')
                ->example('Wildcat'),
        ];
    }

    /**
     * The role, chosen in the import's modal on the Users list. A role's Assigned Users tab
     * passes its own.
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('role_id')
                ->label('Role')
                ->options(fn (): array => resolve(AddPersonToRole::class)->rolesFor(self::actor()))
                ->searchable()
                ->required()
                ->hidden(fn (mixed $livewire): bool => $livewire instanceof UsersRelationManager),
        ];
    }

    /**
     * Does the work and returns no record, so Filament has nothing left to fill or save.
     *
     * @throws RowImportFailedException
     */
    public function resolveRecord(): ?User
    {
        $role = Role::query()->find($this->options['role_id'] ?? null);

        if (! $role instanceof Role) {
            throw new RowImportFailedException('The role no longer exists.');
        }

        /** @var User $actor */
        $actor = $this->import->user;

        try {
            resolve(AddPersonToRole::class)(
                role: $role,
                person: (string) ($this->data['person'] ?? ''),
                actor: $actor,
                firstName: $this->string('first_name'),
                lastName: $this->string('last_name'),
            );
        } catch (PersonNotAdded|AuthorizationException $exception) {
            throw new RowImportFailedException($exception->getMessage());
        }

        return null;
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'People Added to ' . (self::roleName($import) ?? 'Role');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $added = $import->successful_rows;
        $body = Number::format($added) . ' ' . str('person')->plural($added) . ' added to the ' . (self::roleName($import) ?? 'role') . ' role.';

        if ($notAdded = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($notAdded) . ' ' . ($notAdded === 1 ? 'wasn\'t' : 'weren\'t') . ': download the list to see why.';
        }

        return $body;
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    private static function roleName(Import $import): ?string
    {
        return Role::query()->whereKey($import->getOptions()['role_id'] ?? null)->value('name');
    }

    private function string(string $column): ?string
    {
        $value = $this->data[$column] ?? null;

        return is_string($value) && filled($value) ? $value : null;
    }
}
