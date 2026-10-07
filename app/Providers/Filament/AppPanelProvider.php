<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\Http\Controllers\SignInAsController;
use App\Domains\Auth\SignIn;
use App\Filament\App\Clusters\AccountCluster\Pages\Profile;
use App\Filament\App\Starter\Pages\Auth\EmailCodeLogin;
use App\Filament\App\Starter\Pages\Auth\Login;
use App\Filament\App\Starter\Pages\EnvironmentLockdown as EnvironmentLockdownPage;
use App\Http\Middleware\EnvironmentLockdown;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
                url('/support/changelog'),
                url('/support/changelog/*'),
                url('/' . AdministrationPanelProvider::ID),
                url('/' . AdministrationPanelProvider::ID . '/*'),
            ])
            ->id(self::ID)
            ->path(self::ID)
            ->login(Login::class)
            ->routes(function (): void {
                $signIn = resolve(SignIn::class);

                if ($signIn->offers(SignInMethod::EmailCode)) {
                    Route::get('login/email', EmailCodeLogin::class)->name('auth.login-code');
                }

                // One-click sign-in as a seeded user, so local environments need no SSO or email.
                if ($signIn->offers(SignInMethod::SignInAs)) {
                    Route::get('login/as/{username}', SignInAsController::class)->name('auth.login-as');
                }

                Route::get('access-restricted', EnvironmentLockdownPage::class)
                    ->middleware(Authenticate::class)
                    ->name('environment-lockdown');
            })
            ->viteTheme('resources/css/filament/app/theme.css')
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => Blade::render('<x-sentry-browser />'))
            ->renderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => Blade::render('<x-panel-brand />'))
            // After global search: after the theme's environment badge and just ahead of the user menu.
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn (): string => Blade::render('<x-help-menu />'))
            // Sign-in and the lockdown page; see HasSiteHeader.
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn (): string => Blade::render(<<<'BLADE'
                <x-site-header>
                    @auth
                        @livewire(\Filament\Livewire\SimpleUserMenu::class)
                    @endauth
                </x-site-header>
                <x-public-announcement-banner />
                BLADE))
            // The announcement banner, above every page's heading, inside the page's spacing.
            ->renderHook(PanelsRenderHook::PAGE_START, fn (): string => Blade::render('@livewire(\App\Filament\App\Livewire\AnnouncementBanner::class)'))
            ->userMenuItems([
                'account' => Action::make('account')
                    ->label('Account')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => Profile::getUrl(panel: self::ID))
                    ->extraAttributes([
                        'data-testid' => 'account-menu-link',
                    ]),
                'administration' => Action::make('administration')
                    ->label('Administration')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => Filament::getPanel(AdministrationPanelProvider::ID)->getUrl())
                    ->visible(fn (): bool => auth()->user()->canAccessPanel(Filament::getPanel(AdministrationPanelProvider::ID)))
                    ->extraAttributes([
                        'data-testid' => 'admin-panel-link',
                    ]),
                'logout' => fn (Action $action) => $action
                    ->label('Sign Out')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->extraAttributes([
                        'data-testid' => 'sign-out-menu-link',
                    ])
                    ->url(route('logout')),
            ])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->discoverPages(in: app_path('Filament/App/Starter/Pages'), for: 'App\Filament\App\Starter\Pages')
            ->discoverClusters(in: app_path('Filament/App/Clusters'), for: 'App\Filament\App\Clusters')
            // Filament's dashboard renders the widgets in Filament/App/Widgets; replace it with your own page if you need more.
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            // The bell in the top bar. Send one with Notification::make()->...->sendToDatabase($user).
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->plugins([
                NorthwesternTheme::make()
                    ->impersonationBanner()
                    ->withoutAssetRegistration(),
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
                // Panel routes don't run the `web` middleware group, so lockdown is applied here.
                EnvironmentLockdown::class,
            ]);
    }
}
