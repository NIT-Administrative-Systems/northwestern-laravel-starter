<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Domains\User\Models\User;
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
        $environment = e(strtoupper((string) config('app.env')));
        $appName = e((string) config('app.name'));
        $productionUrl = config('platform.production_url');

        return $schema
            ->components([
                Text::make('You do not have permission to access this environment.')
                    ->weight('semibold'),
                Text::make(new HtmlString(
                    "This is the <strong>{$environment}</strong> environment for <strong>{$appName}</strong>, "
                    . 'which is strictly reserved for <strong>Northwestern IT</strong> development and testing purposes.'
                )),
                Callout::make('Why are you seeing this?')
                    ->description('You do not have an assigned role that grants you access to this environment. If you believe this is an error, please reach out to your project contact or the IT Service Desk for assistance.')
                    ->info()
                    ->actions([
                        Action::make('serviceDesk')
                            ->label('IT Service Desk')
                            ->url(self::SERVICE_DESK_URL, shouldOpenInNewTab: true)
                            ->link(),
                    ]),
                Actions::make([
                    Action::make('production')
                        ->label('Go to production environment')
                        ->icon(Heroicon::OutlinedArrowRight)
                        ->url($productionUrl)
                        ->visible(filled($productionUrl)),
                ])->fullWidth(),
            ]);
    }
}
