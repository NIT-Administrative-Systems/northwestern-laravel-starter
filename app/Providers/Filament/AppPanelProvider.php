<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\App\Pages\Auth\EmailCodeLogin;
use App\Filament\App\Pages\Auth\Login;
use App\Filament\App\Pages\EnvironmentLockdown as EnvironmentLockdownPage;
use App\Filament\Navigation\AppNavGroup;
use App\Http\Middleware\EnvironmentLockdown;
use App\Http\Middleware\InjectLivewireAssets;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Northwestern\FilamentTheme\NorthwesternTheme;

/**
 * The end-user panel, and the default panel. Applications build their features here;
 * the administration panel is for back-office work.
 *
 * This panel owns sign-in for the whole application: guests of any panel are sent to
 * its login page.
 */
class AppPanelProvider extends PanelProvider
{
    public const string ID = 'app';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->spa()
            ->spaUrlExceptions([
                url('/auth/*'),
                url('/impersonate/*'),
                url('/support/*'),
                url('/' . AdministrationPanelProvider::ID),
                url('/' . AdministrationPanelProvider::ID . '/*'),
            ])
            ->id(self::ID)
            ->path(self::ID)
            ->login(Login::class)
            ->routes(function (): void {
                if (config('local-auth.enabled')) {
                    Route::get('login/email', EmailCodeLogin::class)->name('auth.login-code');
                }

                Route::get('access-restricted', EnvironmentLockdownPage::class)
                    ->middleware(Authenticate::class)
                    ->name('environment-lockdown');
            })
            ->viteTheme('resources/css/filament/app/theme.css')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('<x-sentry-browser />'))
            ->userMenuItems([
                'administration' => Action::make('administration')
                    ->label('Administration')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => Filament::getPanel(AdministrationPanelProvider::ID)->getUrl())
                    ->visible(fn (): bool => auth()->user()->canAccessPanel(Filament::getPanel(AdministrationPanelProvider::ID)))
                    ->extraAttributes([
                        'data-cy' => 'admin-panel-link',
                    ]),
                'logout' => fn (Action $action) => $action
                    ->label('Sign out')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->extraAttributes([
                        'data-cy' => 'sign-out-menu-link',
                    ])
                    ->url(route('logout')),
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->plugins([
                NorthwesternTheme::make()
                    ->impersonationBanner()
                    ->withoutAssetRegistration(),
            ])
            ->navigationItems([
                NavigationItem::make('Changelog')
                    ->url(fn (): ?string => Route::has('support.changelog.index') ? route('support.changelog.index') : null)
                    ->visible(fn (): bool => Route::has('support.changelog.index'))
                    ->group(AppNavGroup::Help)
                    ->icon(Heroicon::OutlinedNewspaper)
                    ->sort(1),
                NavigationItem::make('Contact Support')
                    ->url(fn (): ?string => Route::has('support.contact.create') ? route('support.contact.create') : null)
                    ->visible(fn (): bool => Route::has('support.contact.create'))
                    ->group(AppNavGroup::Help)
                    ->icon(Heroicon::OutlinedLifebuoy)
                    ->sort(2),
            ])
            ->middleware([
                InjectLivewireAssets::class,
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
                // Panel routes don't run the `web` middleware group, so lockdown is applied here.
                EnvironmentLockdown::class,
            ]);
    }
}
