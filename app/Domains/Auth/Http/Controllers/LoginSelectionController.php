<?php

declare(strict_types=1);

namespace App\Domains\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LoginSelectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function __invoke(Request $request): RedirectResponse|View
    {
        $ssoRoute = $this->ssoRoute();
        $localEnabled = config('local-auth.enabled');

        if (! $localEnabled && ! App::environment('ci')) {
            return redirect(route($ssoRoute));
        }

        return view('auth.login-selection', [
            'ssoRoute' => $ssoRoute,
            'localEnabled' => $localEnabled,
        ]);
    }

    private function ssoRoute(): string
    {
        $webssoConfigured = filled(config('nusoa.sso.apigeeApiKey'))
            || config('nusoa.sso.strategy') === 'forgerock-direct';

        return $webssoConfigured
            ? 'login-websso'
            : 'login-oauth-redirect';
    }
}
