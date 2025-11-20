<?php

declare(strict_types=1);

namespace App\Domains\User\QueryBuilders;

use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Custom query builder for the {{@see User} model.
 *
 * @template TModel of User
 *
 * @extends Builder<TModel>
 */
class UserBuilder extends Builder
{
    /**
     * Restrict the query to SSO authenticated users.
     */
    public function sso(): self
    {
        return $this->where('auth_type', AuthTypeEnum::SSO);
    }

    /**
     * Restrict the query to locally authenticated users.
     */
    public function local(): self
    {
        return $this->where('auth_type', AuthTypeEnum::LOCAL);
    }

    /**
     * Restrict the query to API users.
     */
    public function api(): self
    {
        return $this->where('auth_type', AuthTypeEnum::API);
    }

    /**
     * Add a case-insensitive constraint on the user's email address.
     */
    public function whereEmailEquals(string $email): self
    {
        $normalized = strtolower(trim($email));

        return $this->where('email', 'ilike', $normalized);
    }

    /**
     * Add a case-insensitive constraint on the user's username.
     */
    public function whereUsernameEquals(string $username): self
    {
        $normalized = strtolower(trim($username));

        return $this->where('username', 'ilike', $normalized);
    }

    /**
     * Add a case-insensitive search constraint for any combination of a user's name.
     *
     * Matches:
     * - First name
     * - Last name
     * - "First Last"
     * - "Last, First"
     */
    public function searchByName(string $term): self
    {
        return $this->where(function (Builder $query) use ($term) {
            $query->where('first_name', 'ilike', "%{$term}%")
                ->orWhere('last_name', 'ilike', "%{$term}%")
                ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) ilike ?", ["%{$term}%"])
                ->orWhereRaw("CONCAT_WS(', ', last_name, first_name) ilike ?", ["%{$term}%"]);
        });
    }

    /**
     * Get the first SSO user matching the given email address, or null if none exist.
     */
    public function firstSsoByEmail(string $email): ?User
    {
        return $this->sso()
            ->whereEmailEquals($email)
            ->first();
    }

    /**
     * Get the first LOCAL user matching the given email address, or null if none exist.
     */
    public function firstLocalByEmail(string $email): ?User
    {
        return $this->local()
            ->whereEmailEquals($email)
            ->first();
    }

    /**
     * Get the first user matching the given email address, checking SSO users first
     * and then LOCAL users if no SSO user is found.
     */
    public function firstByEmailSsoThenLocal(string $email): ?User
    {
        return (clone $this)->firstSsoByEmail($email)
            ?? (clone $this)->firstLocalByEmail($email);
    }

    /**
     * Get the first user matching the given email address (SSO first, then LOCAL).
     * If no existing user is found, return a new (unsaved) SSO user instance
     * pre-populated with the normalized email address.
     *
     * This method DOES NOT persist the new instance to the database.
     */
    public function firstExistingByEmailOrNewSso(string $email): User
    {
        $normalized = strtolower(trim($email));

        $existing = $this->firstByEmailSsoThenLocal($normalized);

        if ($existing) {
            return $existing;
        }

        return $this->getModel()->newInstance([
            'email' => $normalized,
            'auth_type' => AuthTypeEnum::SSO,
        ]);
    }

    /**
     * Get the first SSO user matching the given NetID, including soft-deleted users.
     *
     * If a soft-deleted user is found, it will be restored. If no existing user is found,
     * a new (unsaved) SSO user instance is returned with the normalized NetID.
     *
     * This method DOES NOT persist the new instance to the database.
     */
    public function firstExistingSsoByNetIdOrNew(string $netId): User
    {
        $normalized = strtolower(trim($netId));

        $user = $this->withTrashed()
            ->sso()
            ->whereUsernameEquals($normalized)
            ->first();

        if ($user && $user->trashed()) {
            $user->restore();
        }

        return $user ?: $this->getModel()->newInstance([
            'username' => $normalized,
            'auth_type' => AuthTypeEnum::SSO,
        ]);
    }
}
