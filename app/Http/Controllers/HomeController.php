<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\User\Models\User;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Decides what `/` shows. Guests see the landing page; signed-in users are sent to
 * {@see destinationFor()} and never see it.
 *
 * Every sign-in path (single sign-on, email login codes, and the guest redirect for
 * already signed-in users) lands on `/`, so this controller is the one place that
 * decides where signed-in users go. A link to a specific page still wins: sign-in
 * returns users to the page they asked for first.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User) {
            return redirect()->to($this->destinationFor($user));
        }

        return view('public.landing');
    }

    /**
     * Where signed-in users land. Override this to send different users to different
     * places, for example administrators to the administration panel.
     */
    protected function destinationFor(User $user): string
    {
        return Filament::getPanel(AppPanelProvider::ID)->getUrl();
    }
}
