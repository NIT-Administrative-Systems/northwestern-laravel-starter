<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Users\Pages;

use App\Domains\User\Models\User;
use App\Filament\Administration\Resources\Users\UserResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Contracts\Support\Htmlable;

/**
 * A page of a person's record that groups some of their relation managers, one per tab. It's
 * in the record's sub-navigation only when at least one of its managers applies to the person.
 */
abstract class UserRelationsPage extends ManageRelatedRecords
{
    protected static string $resource = UserResource::class;

    /**
     * The relation managers on this page, in tab order.
     *
     * @return list<class-string<RelationManager>>
     */
    abstract protected static function managers(): array;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof User
            && UserResource::canView($record)
            && static::visibleManagers($record) !== [];
    }

    /**
     * @return list<class-string<RelationManager>>
     */
    public function getRelationManagers(): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        return static::visibleManagers($user);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle() . ' — ' . static::getNavigationLabel();
    }

    public function getBreadcrumb(): string
    {
        return (string) static::getNavigationLabel();
    }

    /**
     * @return list<class-string<RelationManager>>
     */
    protected static function visibleManagers(User $user): array
    {
        return array_values(array_filter(
            static::managers(),
            fn (string $manager): bool => $manager::canViewForRecord($user, static::class),
        ));
    }
}
