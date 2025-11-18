<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * This middleware should be used with Filament panels.
 *
 * The Bootstrap UI includes Alpine/Livewire JS in its app.js bundle and has the config/livewire.php inject_assets
 * setting disabled.
 *
 * However, Filament relies on Livewire's asset injection.
 *
 *
 * This middleware can be selectively applied to Filament panels to ensure harmony between both aspects of the site.
 */
class InjectLivewireAssets
{
    /**
     * @param  Request  $request  The incoming HTTP request
     * @param  Closure(Request): (Response)  $next  The next middleware in the pipeline
     */
    public function handle(Request $request, Closure $next): Response
    {
        Livewire::forceAssetInjection();

        return $next($request);
    }
}
