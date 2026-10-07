<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\SignIn;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Signs out, the way the person signed in ({@see SignIn::signOut()}).
 */
class LogoutSelectionController extends Controller
{
    public function __invoke(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->signOut($request);
    }
}
