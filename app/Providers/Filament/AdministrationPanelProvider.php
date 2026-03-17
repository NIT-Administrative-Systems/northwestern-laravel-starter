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
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Northwestern\FilamentTheme\NorthwesternTheme;

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
            ->maxContentWidth(Width::Full)
            ->viteTheme('resources/css/filament/administration/theme.css')
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
                NavigationItem::make('MinIO Console')
                    ->url(config('filesystems.disks.s3.minio_console'), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => filled(config('filesystems.disks.s3.minio_console')) && auth()->user()->can('viewTelescope'))
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
