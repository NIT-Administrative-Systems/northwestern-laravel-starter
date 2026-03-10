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
        $ssoUrl = $this->ssoUrl();
        $localEnabled = config('local-auth.enabled');

        if (! $localEnabled && ! App::environment('ci') && $ssoUrl) {
            return redirect($ssoUrl);
        }

        return view('auth.login-selection', [
            'ssoUrl' => $ssoUrl,
            'localEnabled' => $localEnabled,
        ]);
    }

    private function ssoUrl(): ?string
    {
        $webssoConfigured = filled(config('nusoa.sso.apigeeApiKey'))
            || config('nusoa.sso.strategy') === 'forgerock-direct';

        $entraConfigured = filled(config('services.northwestern-azure.client_id'))
            && filled(config('services.northwestern-azure.client_secret'));

        return match (true) {
            $webssoConfigured => route('login-websso'),
            $entraConfigured => route('login-oauth-redirect'),
            default => null,
        };
    }
}
