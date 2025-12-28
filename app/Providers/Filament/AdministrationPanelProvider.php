<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Navigation\AdministrationNavGroup;
use App\Http\Middleware\InjectLivewireAssets;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use pxlrbt\FilamentEnvironmentIndicator\EnvironmentIndicatorPlugin;

class AdministrationPanelProvider extends PanelProvider
{
    public const string ID = 'administration';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->spa()
            ->spaUrlExceptions([
                url('/auth/*'),
                url('/impersonate/*'),
            ])
            ->id(self::ID)
            ->path(self::ID)
            ->colors([
                'primary' => Color::Purple,
            ])
            ->viteTheme('resources/css/filament/administration/theme.css')
            ->favicon('https://common.northwestern.edu/v8/icons/favicon-32.png')
            ->brandLogo(config('northwestern-theme.lockup'))
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
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                //
            ])
            ->plugins([
                EnvironmentIndicatorPlugin::make()
                    ->visible(! app()->isProduction())
                    ->color(Color::Yellow),
            ])
            ->navigationItems([
                NavigationItem::make('Telescope')
                    ->url('/telescope', shouldOpenInNewTab: true)
                    ->visible(fn (): bool => auth()->user()->can('viewTelescope'))
                    ->group(AdministrationNavGroup::DEBUG)
                    ->icon(Heroicon::OutlinedEye)
                    ->sort(1000),
                NavigationItem::make('MinIO')
                    ->url(config('filesystems.disks.s3.minio_console'), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => auth()->user()->can('viewTelescope'))
                    ->group(AdministrationNavGroup::DEBUG)
                    ->icon(Heroicon::OutlinedCloud)
                    ->sort(1000),
                NavigationItem::make('MailPit')
                    ->url(config('platform.mail-capture.url'))
                    ->visible(fn (): bool => filled(config('platform.mail-capture.url')))
                    ->group(AdministrationNavGroup::DEBUG)
                    ->icon(Heroicon::OutlinedInbox)
                    ->sort(1000),
            ])
            ->middleware([
                InjectLivewireAssets::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->globalSearch()
            ->globalSearchDebounce('500ms');
    }
}
