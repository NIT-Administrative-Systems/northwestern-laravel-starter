<?php

declare(strict_types=1);

namespace App\Domains\User\Support;

use App\Domains\User\Models\User;
use App\Domains\User\QueryBuilders\UserBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Encapsulates reusable user search and option-label behavior for Filament UIs.
 */
class UserSearch
{
    public function __construct(
        protected UserOptionLabel $userOptionLabel,
    ) {
        //
    }

    /**
     * Search users by name, username, and optionally email.
     *
     * @param  null|callable(UserBuilder<User>): void  $modifyQuery
     * @return Collection<int, User>
     */
    public function search(
        string $search,
        ?callable $modifyQuery = null,
        int $limit = 50,
        bool $includeEmail = true,
    ): Collection {
        /** @var UserBuilder<User> $query */
        $query = User::query();

        if ($modifyQuery !== null) {
            $modifyQuery($query);
        }

        $this->apply($query, $search, $includeEmail);

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();
    }

    /**
     * Apply the shared user search constraints to a user query.
     *
     * @param  UserBuilder<User>  $query
     * @return UserBuilder<User>
     */
    public function apply(UserBuilder $query, string $search, bool $includeEmail = true): UserBuilder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (UserBuilder $query) use ($search, $includeEmail): void {
            $query->searchByName($search)
                ->orWhere('username', 'ilike', "%{$search}%");

            if ($includeEmail) {
                $query->orWhere('email', 'ilike', "%{$search}%");
            }
        });
    }

    /**
     * Apply the shared user search constraints to a related user query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyToRelation(
        Builder $query,
        string $relation,
        string $search,
        bool $includeEmail = true,
        string $boolean = 'and',
    ): Builder {
        $method = $boolean === 'or' ? 'orWhereHas' : 'whereHas';

        return $query->{$method}(
            $relation,
            function (Builder $userQuery) use ($search, $includeEmail): void {
                /** @var UserBuilder<User> $userQuery */
                $this->apply($userQuery, $search, $includeEmail);
            },
        );
    }

    /**
     * Return search results as Filament-ready option arrays.
     *
     * @param  null|callable(UserBuilder<User>): void  $modifyQuery
     * @return array<int, string>
     */
    public function options(
        string $search,
        string $format = UserOptionLabel::FormatFullName,
        bool $withUsername = true,
        ?callable $modifyQuery = null,
        int $limit = 50,
        bool $includeEmail = true,
    ): array {
        return $this->search($search, $modifyQuery, $limit, $includeEmail)
            ->mapWithKeys(fn (User $user): array => [
                $user->getKey() => $this->userOptionLabel->for($user, $format, $withUsername),
            ])
            ->all();
    }

    /**
     * Resolve a single selected user ID into its display label.
     */
    public function label(int|string|null $id, string $format = UserOptionLabel::FormatFullName, bool $withUsername = true): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        /** @var User|null $user */
        $user = User::find($id);

        return $this->userOptionLabel->forNullable($user, $format, $withUsername);
    }
}
