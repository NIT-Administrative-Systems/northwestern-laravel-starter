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
 * Decides what `/` shows: the landing page for guests, and
 * {@see destinationFor()} for people who are signed in.
 *
 * Every sign-in lands on `/` unless the person first asked for another
 * page, so this is the one place deciding where signed-in people go.
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
     * Get where a signed-in person lands. Override this
     * to send different people to different places,
     * such as administrators to Administration.
     */
    protected function destinationFor(User $user): string
    {
        return Filament::getPanel(AppPanelProvider::ID)->getUrl();
    }
}
