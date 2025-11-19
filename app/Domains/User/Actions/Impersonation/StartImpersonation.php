<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Impersonation;

use App\Domains\User\Models\User;
use Lab404\Impersonate\Exceptions\InvalidUserProvider;
use Lab404\Impersonate\Exceptions\MissingUserProvider;
use Lab404\Impersonate\Services\ImpersonateManager;

class StartImpersonation
{
    public function __construct(
        private readonly ImpersonateManager $manager,
    ) {
        //
    }

    /**
     * @throws MissingUserProvider
     * @throws InvalidUserProvider
     */
    public function __invoke(User $user, string|int $userIdToImpersonate, ?string $guardName = null): string
    {
        abort_unless($user->canImpersonate(), 403);

        $guardName ??= $this->manager->getDefaultSessionGuard();

        abort_if(($this->manager->getCurrentAuthGuardName() === $guardName) && (int) $userIdToImpersonate === $user->getAuthIdentifier(), 403);

        abort_if($this->manager->isImpersonating(), 403);

        /** @var User $userToImpersonate */
        $userToImpersonate = $this->manager->findUserById((int) $userIdToImpersonate, $guardName);
        abort_if(! $userToImpersonate, 404, 'User not found.');

        if ($userToImpersonate->canBeImpersonated() &&
            $this->manager->take($user, $userToImpersonate, $guardName)
        ) {
            return $this->manager->getTakeRedirectTo();
        }

        return 'back';
    }
}
