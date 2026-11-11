<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Lifecycle;

use App\Domains\Core\Enums\AuditEvent;
use App\Domains\User\AccountStatusAccess;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Restores an account {@see DeactivateUser} deactivated. The credentials it revoked stay revoked:
 * the person creates new ones, or an administrator issues new service clients.
 */
readonly class ReactivateUser
{
    public function __construct(
        private AccountStatusAccess $access,
    ) {
    }

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, User $actor): void
    {
        $this->access->decide($actor, $user)->authorize();

        if (! $user->trashed()) {
            return;
        }

        $deletedAt = $user->deleted_at?->toIso8601String();

        $user->restoreQuietly();

        $user->recordAuditEvent(
            AuditEvent::AccountReactivated,
            new: ['deleted_at' => null],
            old: ['deleted_at' => $deletedAt],
        );
    }
}
