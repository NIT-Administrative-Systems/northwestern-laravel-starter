<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Since you'll often want information about an authenticated user's roles
 * and permissions for authorization, they are eager loaded upfront.
 *
 * If you find that you need to eager load more relationships, you can
 * add them to the `withQuery` method below.
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
