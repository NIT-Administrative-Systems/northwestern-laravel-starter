<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Lifecycle;

use App\Domains\Api\Actions\RevokeAllCredentials;
use App\Domains\Auth\Actions\EndSessions;
use App\Domains\Core\Enums\AuditEvent;
use App\Domains\User\AccountStatusAccess;
use App\Domains\User\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Deactivates an account: soft-deletes it, revokes everything it can call the API with and signs
 * it out everywhere. The account and its history stay, and {@see ReactivateUser} restores it.
 *
 * Recorded as {@see AuditEvent::AccountDeactivated} rather than Eloquent's `deleted`, so it's
 * audited from the scheduler too, where model events aren't.
 */
readonly class DeactivateUser
{
    public function __construct(
        private AccountStatusAccess $access,
        private RevokeAllCredentials $revokeAllCredentials,
        private EndSessions $endSessions,
    ) {
    }

    /**
     * @param  User|null  $actor  The administrator; null when the system deactivates the account
     * @param  array<string, mixed>  $context  Recorded with the audit, such as why it was deactivated
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, ?User $actor, array $context = []): void
    {
        $this->access->decide($actor, $user)->authorize();

        if ($user->trashed()) {
            return;
        }

        DB::transaction(function () use ($user, $context): void {
            // Quietly, so the delete isn't audited as a plain delete; the credentials are revoked below.
            $user->deleteQuietly();

            ($this->revokeAllCredentials)($user);
            ($this->endSessions)($user);

            $user->recordAuditEvent(
                AuditEvent::AccountDeactivated,
                new: ['deleted_at' => $user->deleted_at?->toIso8601String()],
                old: ['deleted_at' => null],
                context: $context,
            );
        });
    }
}
