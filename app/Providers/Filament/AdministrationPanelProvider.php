<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Administration\Navigation\AdministrationNavGroup;
use App\Filament\App\Clusters\AccountCluster\Pages\Profile;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
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
            ->strictAuthorization()
            ->maxContentWidth(Width::Full)
            ->viteTheme('resources/css/filament/administration/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('<x-sentry-browser />'))
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => Blade::render('<x-panel-brand />'))
            // After global search, as in the app panel: just ahead of the user menu.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): string => Blade::render('<x-help-menu />'))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn (): string => Blade::render('<x-sidebar-panel-name name="Administration" />'))
            ->userMenuItems([
                'app' => Action::make('app')
                    ->label(fn (): string => 'Back to ' . config('app.name'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->url(fn (): string => Filament::getPanel(AppPanelProvider::ID)->getUrl())
                    ->extraAttributes([
                        'data-testid' => 'app-panel-link',
                    ]),
                'account' => Action::make('account')
                    ->label('Account')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => Profile::getUrl(panel: AppPanelProvider::ID))
                    ->extraAttributes([
                        'data-testid' => 'account-menu-link',
                    ]),
                'logout' => fn (Action $action) => $action
                    ->label('Sign Out')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->extraAttributes([
                        'data-testid' => 'sign-out-menu-link',
                    ])
                    ->url(route('logout')),
            ])
            ->discoverResources(in: app_path('Filament/Administration/Resources'), for: 'App\Filament\Administration\Resources')
            ->discoverPages(in: app_path('Filament/Administration/Pages'), for: 'App\Filament\Administration\Pages')
            ->discoverClusters(in: app_path('Filament/Administration/Clusters'), for: 'App\Filament\Administration\Clusters')
            ->discoverWidgets(in: app_path('Filament/Administration/Widgets'), for: 'App\Filament\Administration\Widgets')
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
