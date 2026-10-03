<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Navigation\AdministrationNavGroup;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Northwestern\FilamentTheme\NorthwesternTheme;

class AdministrationPanelProvider extends PanelProvider
{
    public const string ID = 'administration';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->spa()
            ->spaUrlExceptions([
                url('/auth/*'),
                url('/impersonate/*'),
            ])
            ->id(self::ID)
            ->path(self::ID)
            ->maxContentWidth(Width::Full)
            ->viteTheme('resources/css/filament/administration/theme.css')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('<x-sentry-browser />'))
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => Blade::render('<x-panel-brand />'))
            ->userMenuItems([
                'logout' => fn (Action $action) => $action
                    ->label('Sign out')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->extraAttributes([
                        'data-cy' => 'sign-out-menu-link',
                    ])
                    ->url(route('logout')),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                //
            ])
            ->plugins([
                NorthwesternTheme::make()
                    ->impersonationBanner()
                    // A back-office panel; the footer is for the pages end users see.
                    ->footer(false)
                    ->withoutAssetRegistration(),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->navigationItems([
                NavigationItem::make('Telescope')
                    ->url('/telescope', shouldOpenInNewTab: true)
                    ->visible(fn (): bool => auth()->user()->can('viewTelescope'))
                    ->group(AdministrationNavGroup::DeveloperTools)
                    ->icon(Heroicon::OutlinedEye)
                    ->sort(1001),
                NavigationItem::make('RustFS Console')
                    ->url(config('filesystems.disks.s3.console_url'), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => filled(config('filesystems.disks.s3.console_url')) && auth()->user()->can('viewTelescope'))
                    ->group(AdministrationNavGroup::DeveloperTools)
                    ->icon(Heroicon::OutlinedCloud)
                    ->sort(1002),
                NavigationItem::make('MailPit')
                    ->url(config('platform.mail-capture.url'), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => filled(config('platform.mail-capture.url')) && auth()->user()->can('viewTelescope'))
                    ->group(AdministrationNavGroup::DeveloperTools)
                    ->icon(Heroicon::OutlinedEnvelope)
                    ->sort(1003),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->globalSearch()
            // Resources join global search only by declaring $isGloballySearchable = true, so each
            // new resource does not add its queries to every global search keystroke.
            ->globalSearchResourceOptIn()
            ->globalSearchDebounce('750ms');
    }
}
