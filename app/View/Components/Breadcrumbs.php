<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Breadcrumbs extends Component
{
    /**
     * @param  array<string, string>  $breadcrumbs  An associative array of breadcrumb routes and the text to display.
     */
    public function __construct(
        public array $breadcrumbs
    ) {
    }

    public function render(): View
    {
        return view('components.breadcrumbs');
    }
}
