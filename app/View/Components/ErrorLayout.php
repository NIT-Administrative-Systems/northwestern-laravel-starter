<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ErrorLayout extends Component
{
    /**
     * @param  string  $title  The `<title>` and heading text for the error page.
     */
    public function __construct(
        public readonly string $title,
    ) {
        //
    }

    public function render(): View
    {
        return view('errors.layout');
    }
}
