<?php

declare(strict_types=1);

namespace App\Filament\App\Starter\Pages;

use App\Domains\User\Models\User;
use App\Filament\App\Starter\Pages\Concerns\HasSiteHeader;
use App\Http\Middleware\EnvironmentLockdown as EnvironmentLockdownMiddleware;
use Filament\Actions\Action;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Shown to signed-in users who have no role granting access to a locked-down
 * environment. {@see EnvironmentLockdownMiddleware} redirects them here.
 */
class EnvironmentLockdown extends SimplePage
{
    use HasSiteHeader;

    private const string SERVICE_DESK_URL = 'https://www.it.northwestern.edu/support/service-desk/';

    protected static ?string $title = 'Access Restricted';

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->non_default_roles->isNotEmpty()) {
            $this->redirect('/');
        }
    }

    public function content(Schema $schema): Schema
    {
        $environment = e((string) config('app.env'));
        $appName = e((string) config('app.name'));
        $productionUrl = config('platform.production_url');

        return $schema
            ->components([
                Text::make('You don\'t have access to this environment.')
                    ->weight('semibold'),
                Text::make(new HtmlString(
                    "This is the <strong>{$environment}</strong> environment of <strong>{$appName}</strong>, for development and testing."
                )),
                // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
                Callout::make()
                    ->description(new HtmlString(
                        '<strong>Why am I seeing this?</strong> Your account doesn\'t have a role that gives access here. '
                        . 'If you need access, ask your project contact or the IT Service Desk.'
                    ))
                    ->info()
                    ->actions([
                        Action::make('serviceDesk')
                            ->label('IT Service Desk')
                            ->url(self::SERVICE_DESK_URL, shouldOpenInNewTab: true)
                            ->link(),
                    ]),
                Actions::make([
                    Action::make('production')
                        ->label('Go to ' . config('app.name'))
                        ->icon(Heroicon::OutlinedArrowRight)
                        ->url($productionUrl)
                        ->visible(filled($productionUrl)),
                ])->fullWidth(),
            ]);
    }
}
