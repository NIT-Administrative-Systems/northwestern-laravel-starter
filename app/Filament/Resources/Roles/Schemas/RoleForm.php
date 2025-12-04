<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Schemas;

use App\Domains\User\Enums\PermissionEnum;
use App\Domains\User\Enums\PermissionScopeEnum;
use App\Domains\User\Enums\RoleTypeEnum;
use App\Domains\User\Models\Permission;
use App\Domains\User\Models\RoleType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $apiPermissions = [];
        $nonApiPermissions = [];
        $systemPermissions = [];

        $permissionScopeBadgeHTML = collect(PermissionScopeEnum::cases())->mapWithKeys(function (PermissionScopeEnum $scope) {
            return [$scope->value => $scope->getBadgeHTML()];
        });

        foreach (Permission::all() as $permission) {
            $permissionData = [
                'label' => $permission->label,
                'description' => $permission->description,
                'scopeBadgeHTML' => $permissionScopeBadgeHTML[$permission->scope->value],
            ];

            if ($permission->system_managed) {
                $systemPermissions[$permission->name] = $permissionData;
            } elseif ($permission->api_relevant) {
                $apiPermissions[$permission->name] = $permissionData;
            } else {
                $nonApiPermissions[$permission->name] = $permissionData;
            }
        }

        /** @var Collection<int, RoleType> $allRoleTypes */
        $allRoleTypes = once(fn () => RoleType::all());
        $allRoleTypeOptions = $allRoleTypes->pluck('label', 'id');

        $roleTypeOptions = $allRoleTypes
            ->reject(fn (RoleType $roleType) => $roleType->slug === RoleTypeEnum::SYSTEM_MANAGED)
            ->pluck('label', 'id');

        return $schema
            ->components([
                Section::make('Details')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->unique(
                                        table: 'roles',
                                        column: 'name',
                                        ignorable: fn ($record) => $record,
                                    )
                                    ->maxLength(255),

                                Select::make('role_type_id')
                                    ->label('Role Type')
                                    ->options(function ($record) use ($roleTypeOptions, $allRoleTypeOptions) {
                                        // A System Managed role can never be manually created.
                                        // When editing existing roles, show all types.
                                        if ($record?->isSystemManagedType()) {
                                            return $allRoleTypeOptions;
                                        }

                                        return $roleTypeOptions;
                                    })
                                    ->required()
                                    ->disabled(fn ($record) => $record?->isSystemManagedType())
                                    ->helperText(function ($state) {
                                        if (! $state) {
                                            return null;
                                        }

                                        $roleType = RoleType::find($state);

                                        return $roleType?->slug?->getDescription();
                                    })
                                    ->live(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Permissions')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->description(function ($get, $record) {
                        $roleTypeId = $get('role_type_id');
                        if (! $roleTypeId) {
                            return 'The permissions that users with this role should have.';
                        }

                        $roleType = $record->role_type ?? once(fn () => RoleType::find($roleTypeId));
                        if ($roleType?->slug === RoleTypeEnum::API_INTEGRATION) {
                            return 'Select data access permissions appropriate for API integrations. UI-specific permissions are not available for API roles.';
                        }

                        return 'The permissions that users with this role should have.';
                    })
                    ->collapsible()
                    ->schema(array_filter([
                        // For API_INTEGRATION roles, only show API permissions
                        filled($apiPermissions) ? CheckboxList::make('api_permissions')
                            ->label('API Permissions')
                            ->options(collect($apiPermissions)->mapWithKeys(fn ($data, $value) => [
                                $value => new HtmlString($data['label'] . $data['scopeBadgeHTML']),
                            ])->toArray())
                            ->descriptions(collect($apiPermissions)->mapWithKeys(fn ($data, $value) => [
                                $value => $data['description'],
                            ])->toArray())
                            ->columns()
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->afterStateHydrated(function (CheckboxList $component, $record) use ($apiPermissions) {
                                if ($record) {
                                    $existingPermissions = once(fn () => $record->permissions->pluck('name')->toArray());
                                    $apiOptions = array_keys(collect($apiPermissions)->toArray());
                                    $component->state(array_intersect($existingPermissions, $apiOptions));
                                }
                            })
                            ->visible(function ($get, $record) {
                                $roleTypeId = $get('role_type_id');
                                if (! $roleTypeId) {
                                    return false;
                                }
                                $roleType = $record->role_type ?? once(fn () => RoleType::find($roleTypeId));

                                return $roleType?->slug === RoleTypeEnum::API_INTEGRATION;
                            })
                            ->dehydrated(false)
                            ->columnSpanFull() : null,

                        // For non-API roles, show regular permissions
                        filled($nonApiPermissions) ? CheckboxList::make('regular_permissions')
                            ->label('Permissions')
                            ->options(collect($nonApiPermissions)->mapWithKeys(fn ($data, $value) => [
                                $value => new HtmlString($data['label'] . $data['scopeBadgeHTML']),
                            ])->toArray())
                            ->descriptions(collect($nonApiPermissions)->mapWithKeys(fn ($data, $value) => [
                                $value => $data['description'],
                            ])->toArray())
                            ->columns()
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->afterStateHydrated(function (CheckboxList $component, $record) use ($nonApiPermissions) {
                                if ($record) {
                                    $existingPermissions = once(fn () => $record->permissions->pluck('name')->toArray());
                                    $regularOptions = array_keys(collect($nonApiPermissions)->toArray());
                                    $component->state(array_intersect($existingPermissions, $regularOptions));
                                }
                            })
                            ->visible(function ($get, $record) {
                                $roleTypeId = $get('role_type_id');
                                if (! $roleTypeId) {
                                    return true;
                                }
                                $roleType = $record->role_type ?? once(fn () => RoleType::find($roleTypeId));

                                return $roleType?->slug !== RoleTypeEnum::API_INTEGRATION;
                            })
                            ->dehydrated(false)
                            ->columnSpanFull() : null,

                        // System Permissions - only visible to users with MANAGE_ALL and hidden for API roles
                        filled($systemPermissions) ? CheckboxList::make('system_permissions')
                            ->label('System Managed Permissions')
                            ->helperText(new HtmlString(<<<'HTML'
        <div class="mt-3 rounded-md border border-red-500/40 bg-red-500/5 px-4 py-3 text-xs leading-relaxed text-red-200">
            <p class="font-semibold text-red-200">Sensitive Permissions</p>
            <p class="mt-1">
                These permissions are sensitive and should not be given out broadly. They are only visible to users with the
                <span class="font-semibold">"Manage All"</span> permission.
            </p>
        </div>
    HTML))
                            ->options(collect($systemPermissions)->mapWithKeys(fn ($data, $value) => [
                                $value => new HtmlString($data['label'] . $data['scopeBadgeHTML']),
                            ])->toArray())
                            ->descriptions(collect($systemPermissions)->mapWithKeys(fn ($data, $value) => [
                                $value => $data['description'],
                            ])->toArray())
                            ->visible(function ($get, $record) {
                                if (! auth()->user()->hasPermissionTo(PermissionEnum::MANAGE_ALL)) {
                                    return false;
                                }

                                $roleTypeId = $get('role_type_id');
                                if (! $roleTypeId) {
                                    return true;
                                }
                                $roleType = $record->role_type ?? once(fn () => RoleType::find($roleTypeId));

                                return $roleType?->slug !== RoleTypeEnum::API_INTEGRATION;
                            })
                            ->columns()
                            ->gridDirection('row')
                            ->afterStateHydrated(function (CheckboxList $component, $record) use ($systemPermissions) {
                                if ($record) {
                                    $existingPermissions = once(fn () => $record->permissions->pluck('name')->toArray());
                                    $systemOptions = array_keys($systemPermissions);
                                    $component->state(array_intersect($existingPermissions, $systemOptions));
                                }
                            })
                            ->dehydrated(false)
                            ->columnSpanFull() : null,
                    ]))
                    ->columnSpanFull(),
            ]);
    }
}
