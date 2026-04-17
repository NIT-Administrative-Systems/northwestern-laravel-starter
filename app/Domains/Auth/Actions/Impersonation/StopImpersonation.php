<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Impersonation;

use Illuminate\Support\Facades\Session;
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

        $returnUrl = Session::pull('impersonation.return_url');

        return $returnUrl ?: $this->manager->getLeaveRedirectTo();
    }
}
