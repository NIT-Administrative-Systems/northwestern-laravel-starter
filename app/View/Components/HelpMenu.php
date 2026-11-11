<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Filament\App\Starter\Pages\Announcements;
use App\Filament\App\Starter\Pages\ContactSupport;
use App\Providers\Filament\AppPanelProvider;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * The Help menu in both panels' top bars and the header of public and error pages.
 * Each item shows only while it's available, and with none the menu is left out.
 */
class HelpMenu extends Component
{
    public function render(): View
    {
        return view('components.help-menu', ['items' => $this->items()]);
    }

    /**
     * Get the items: Announcements and Contact Support for people who are signed in,
     * the changelog while its routes exist, and documentation when its URL is set.
     *
     * @return list<array{label: string, url: string, icon: Heroicon, external: bool}>
     */
    private function items(): array
    {
        // Error pages render the menu while the database may be down.
        $signedIn = rescue(fn (): bool => auth()->check(), false, report: false);
        $documentationUrl = config('support.documentation_url');

        return array_values(array_filter([
            $signedIn ? [
                'label' => 'Announcements',
                'url' => Announcements::getUrl(panel: AppPanelProvider::ID),
                'icon' => Heroicon::OutlinedMegaphone,
                'external' => false,
            ] : null,
            Route::has('support.changelog.index') ? [
                'label' => 'Changelog',
                'url' => route('support.changelog.index'),
                'icon' => Heroicon::OutlinedNewspaper,
                'external' => false,
            ] : null,
            $signedIn && ContactSupport::canAccess() ? [
                'label' => 'Contact Support',
                'url' => ContactSupport::getUrl(panel: AppPanelProvider::ID),
                'icon' => Heroicon::OutlinedLifebuoy,
                'external' => false,
            ] : null,
            is_string($documentationUrl) && filled($documentationUrl) ? [
                'label' => 'Documentation',
                'url' => $documentationUrl,
                'icon' => Heroicon::OutlinedBookOpen,
                'external' => true,
            ] : null,
        ]));
    }
}
