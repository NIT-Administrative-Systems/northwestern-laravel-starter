<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ErrorLayout extends Component
{
    /**
     * @param  string  $title  The `<title>` and heading text for the error page.
     * @param  bool  $navigation  Render on the public layout, with the Help menu and Sign in or
     *                            the user menu. Off for errors where the application itself may
     *                            not be working (maintenance), which use the bare error layout.
     */
    public function __construct(
        public readonly string $title,
        public readonly bool $navigation = true,
    ) {
        //
    }

    public function render(): View
    {
        return view('errors.layout');
    }
}
