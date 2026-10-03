<?php

declare(strict_types=1);

namespace App\Filament\App\Pages;

use App\Providers\Filament\AppPanelProvider;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;

/**
 * The app panel's dashboard.
 *
 * Starter placeholder: everything in content() is a setup checklist for developers,
 * not part of an application. Replace it with your application's dashboard (widgets
 * from getWidgets(), or your own content), and delete ComponentGallery when you no
 * longer need it.
 */
class Dashboard extends BaseDashboard
{
    private const string DOCS_URL = 'https://laravel-starter.entapp.northwestern.edu/';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
                Callout::make()
                    ->description(new HtmlString(
                        '<strong>Starter placeholder.</strong> This dashboard comes from the Northwestern Laravel Starter. '
                        . 'Replace it with your application\'s dashboard in <code>app/Filament/App/Pages/Dashboard.php</code>.'
                    ))
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->warning()
                    ->actions([
                        Action::make('gallery')
                            ->label('Component gallery')
                            ->icon(Heroicon::OutlinedSwatch)
                            ->url(fn (): string => ComponentGallery::getUrl())
                            ->visible(fn (): bool => ComponentGallery::canAccess())
                            ->link(),
                        Action::make('documentation')
                            ->label('Starter documentation')
                            ->icon(Heroicon::OutlinedBookOpen)
                            ->url(self::DOCS_URL, shouldOpenInNewTab: true)
                            ->link(),
                    ]),

                Section::make('Getting started')
                    ->description('Steps most applications take first. The starter ticks the ones it can detect from your configuration.')
                    ->schema([
                        $this->step(
                            'unit',
                            'Set your unit\'s details',
                            'The footer of every page and email shows your unit\'s name, address and contacts.',
                            filled(config('northwestern-filament-theme.unit.name')),
                            'getting-started/initial-customization/#6-your-units-details',
                        ),
                        $this->step(
                            'sso',
                            'Configure Northwestern single sign-on',
                            'WebSSO or Entra ID, so people can sign in with their NetID.',
                            Route::has('login-websso') || Route::has('login-oauth-redirect'),
                            'features/authentication/',
                        ),
                        $this->step(
                            'landing',
                            'Replace the landing page',
                            'Guests see resources/views/public/landing.blade.php at /.',
                            null,
                            'architecture/ui-architecture/#the-public-layout',
                        ),
                        $this->step(
                            'feature',
                            'Build your first feature',
                            'Resources and pages in app/Filament/App/ appear in this panel\'s sidebar.',
                            Filament::getPanel(AppPanelProvider::ID)->getResources() !== [],
                            'architecture/ui-architecture/#the-app-panel',
                        ),
                        $this->step(
                            'support',
                            'Set up Contact Support',
                            'Turn on the contact form and send requests to your team or TeamDynamix.',
                            config('support.enabled') && config('support.mail.to') !== 'your-team@northwestern.edu',
                            'features/support-tickets/',
                        ),
                        $this->step(
                            'sentry',
                            'Connect Sentry',
                            'Error and performance reporting for the server and the browser.',
                            filled(config('sentry.dsn')),
                            'features/sentry/',
                        ),
                    ]),
            ]);
    }

    /**
     * One checklist row. $done is null for steps the starter cannot detect.
     */
    private function step(string $key, string $title, string $description, ?bool $done, string $docsPath): Flex
    {
        return Flex::make([
            Icon::make(match ($done) {
                true => Heroicon::CheckCircle,
                false => Heroicon::OutlinedStopCircle,
                null => Heroicon::OutlinedQuestionMarkCircle,
            })
                ->color($done ? 'success' : 'gray')
                ->tooltip(match ($done) {
                    true => 'Done',
                    false => 'Not yet',
                    null => 'The starter can\'t detect this one',
                })
                ->grow(false),
            Group::make([
                Text::make($title)->weight(FontWeight::SemiBold),
                Text::make($description),
            ])->dense(),
            Actions::make([
                Action::make("docs_{$key}")
                    ->label('Docs')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->iconPosition(IconPosition::After)
                    ->url(self::DOCS_URL . $docsPath, shouldOpenInNewTab: true)
                    ->link(),
            ])->grow(false),
        ])->extraAttributes(['data-cy' => "starter-step-{$key}"]);
    }
}
