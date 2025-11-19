<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Impersonation;

use Lab404\Impersonate\Services\ImpersonateManager;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StopImpersonation
{
    public function __construct(
        private readonly ImpersonateManager $manager,
    ) {
        //
    }

    /**
     * @throws HttpException
     * @throws NotFoundHttpException
     */
    public function __invoke(): string
    {
        abort_unless($this->manager->isImpersonating(), 403);

        $this->manager->leave();

        // Retrieve the stored return URL and clear it from session
        $returnUrl = session()->pull('impersonation.return_url');

        // If we have a stored URL, return it; otherwise fall back to default
        return $returnUrl ?: $this->manager->getLeaveRedirectTo();
    }
}
