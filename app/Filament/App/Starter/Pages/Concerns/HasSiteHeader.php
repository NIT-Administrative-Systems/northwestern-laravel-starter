<?php

declare(strict_types=1);

namespace App\Filament\App\Starter\Pages\Concerns;

/**
 * For the app panel's simple pages (sign-in and the lockdown page), which render
 * <x-site-header> above the card through the SIMPLE_LAYOUT_START render hook.
 *
 * The header carries the wordmark, the application name and the user menu, so
 * Filament's own logo and simple-page user menu are turned off.
 */
trait HasSiteHeader
{
    public function hasLogo(): bool
    {
        return false;
    }

    public function hasTopbar(): bool
    {
        return false;
    }
}
