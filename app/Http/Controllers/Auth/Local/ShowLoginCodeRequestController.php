<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Local;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ShowLoginCodeRequestController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(config('auth.local.enabled'), 404);

        return view('auth.login-code-request');
    }
}
