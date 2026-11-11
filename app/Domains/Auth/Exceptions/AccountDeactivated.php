<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use App\Domains\Auth\SignIn;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A deactivated account tried to sign in. {@see SignIn::complete()}
 * throws it for every method, after ending the session, and the
 * person sees a page saying who can reactivate the account.
 */
final class AccountDeactivated extends HttpException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_FORBIDDEN, 'The account is deactivated.');
    }

    public function render(): Response
    {
        return response()->view('errors.account-deactivated', status: $this->getStatusCode());
    }
}
