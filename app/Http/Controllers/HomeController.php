<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * This controller serves as the entry point for authenticated users accessing the root URL.
 * By default, it renders the `default-home` view, but it can be extended to provide more
 * personalized routing based on the user's roles, permissions, or other logic.
 */
class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function __invoke(): View|RedirectResponse
    {
        return view('default-home');
    }
}
