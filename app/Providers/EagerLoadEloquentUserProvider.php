<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Loads the signed-in user with their roles,
 * role types and permissions, which almost
 * every authorization check reads. Add
 * any other relationship that every
 * request needs to `withQuery()`.
 */
class EagerLoadEloquentUserProvider extends EloquentUserProvider
{
    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(HasherContract $hasher, $model)
    {
        parent::__construct($hasher, $model);

        $this->withQuery(function (Builder $query) {
            $query->with(['roles.role_type', 'roles.permissions']);
        });
    }
}
