<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Core\Services\DateTimeFormatter;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for view-related customizations.
 *
 * This provider registers custom Blade directives and other view enhancements
 * that are used throughout the application.
 */
class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $dateTimeFormatter = resolve(DateTimeFormatter::class);
        Blade::directive('datetime', $dateTimeFormatter->buildDatetimeDirective());
    }
}
