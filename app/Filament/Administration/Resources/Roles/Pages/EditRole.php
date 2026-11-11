<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Roles\Pages;

use App\Domains\Access\Actions\SyncRolePermissions;
use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Roles\RoleResource;
use App\Filament\Administration\Resources\Roles\Schemas\RoleForm;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Northwestern\SysDev\Chassis\Formatting\CountInWords;

/**
 * @property-read Role $record
 */
class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected static ?string $navigationLabel = 'Details';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    /** @var list<string> The permissions chosen in the lists the form showed. */
    protected array $chosenPermissions = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_if($this->record->isSystemManagedType(), 403, 'System Managed roles cannot be edited.');
    }

    protected function getHeaderActions(): array
    {
        return [
            // In a menu, so a red button doesn't sit alone above the form.
            ActionGroup::make([
                DeleteAction::make()
                    ->authorize(fn () => ! $this->record->isSystemManagedType() && Gate::allows('delete', $this->record))
                    ->modalDescription(function (Role $record): HtmlString {
                        $userCount = $record->users()->count();

                        if ($userCount === 0) {
                            return new HtmlString('Are you sure you want to delete this role? This action cannot be undone.');
                        }

                        $userText = CountInWords::of($userCount, 'user');

                        return new HtmlString(
                            "<span class=\"text-danger-600 dark:text-danger-400 font-medium\">This role is assigned to {$userText}.</span><br><br>" .
                            'Deleting it will revoke their permissions granted by this role. This action cannot be undone.'
                        );
                    })
                    ->before(function (Role $record) {
                        // Remove the role from all assigned users before deleting
                        $record->users()
                            ->lazyById(100)
                            ->each(function (User $user) use ($record) {
                                $user->removeRoleWithAudit($record, RoleModificationOrigin::RemovedByDeletion);
                            });
                    }),
            ]),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->chosenPermissions = array_values(Arr::flatten(Arr::only($data, RoleForm::PERMISSION_LISTS)));

        return [...Arr::except($data, RoleForm::PERMISSION_LISTS), 'guard_name' => 'web'];
    }

    protected function afterSave(): void
    {
        resolve(SyncRolePermissions::class)($this->record, $this->chosenPermissions, auth()->user());
    }
}
