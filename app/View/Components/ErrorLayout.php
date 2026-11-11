<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ErrorLayout extends Component
{
    /**
     * @param  string  $title  The `<title>` and heading text for the error page.
     * @param  int|null  $code  The HTTP status, shown above the heading so people can quote it, and
     *                          the one that decides the page's next step ({@see action()}).
     * @param  bool  $navigation  Render on the public layout, with the Help menu and Sign in or
     *                            the user menu. Off for errors where the application itself may
     *                            not be working (maintenance), which use the bare error layout.
     */
    public function __construct(
        public readonly string $title,
        public readonly ?int $code = null,
        public readonly bool $navigation = true,
    ) {
        //
    }

    public function render(): View
    {
        return view('errors.layout', ['action' => $this->action()]);
    }

    /**
     * The next step for the status: sign in, reload the page the person came from, go back, or
     * go to the home page. Maintenance (503) has none, since every page is down, and neither has
     * a page without a status, such as database-paused, which reloads itself.
     *
     * @return array{label: string, url: string}|null
     */
    private function action(): ?array
    {
        return match ($this->code) {
            null, 503 => null,
            401 => ['label' => 'Sign In', 'url' => route('filament.app.auth.login')],
            419 => ['label' => 'Reload the Page', 'url' => $this->previousUrl()],
            429 => ['label' => 'Go Back', 'url' => $this->previousUrl()],
            default => ['label' => 'Go to the Home Page', 'url' => url('/')],
        };
    }

    /**
     * The page the person came from, when it is on this application, so the link never leads
     * elsewhere; otherwise the home page.
     */
    private function previousUrl(): string
    {
        $previous = url()->previous();

        return parse_url($previous, PHP_URL_HOST) === request()->getHost() ? $previous : url('/');
    }
}
