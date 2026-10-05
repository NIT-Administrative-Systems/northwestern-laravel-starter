<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Not you? Switch account" on the OAuth consent screen: signs out of this application and
 * returns to sign-in, then back to the consent screen as whoever signs in.
 *
 * It signs out of this application only. With single sign-on, the identity provider may
 * still be signed in and sign the same person straight back in.
 */
class SwitchOAuthAccountController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $returnTo = $request->string('return_to')->toString();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Only ever back to the consent screen: never an address the form could be given.
        if (str_starts_with($returnTo, url('/oauth/authorize') . '?')) {
            $request->session()->put('url.intended', $returnTo);
        }

        return redirect(Filament::getPanel(AppPanelProvider::ID)->getLoginUrl());
    }
}
