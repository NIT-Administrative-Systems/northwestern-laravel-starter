<?php

declare(strict_types=1);

namespace App\Filament\App\Starter\Pages\Auth;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\SignIn;
use App\Domains\User\Models\User;
use App\Filament\App\Starter\Pages\Concerns\HasSiteHeader;
use Database\Seeders\Sample\DemoUserSeeder;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Lists the sign-in methods this application offers ({@see SignIn::methods()}): Northwestern
 * single sign-on through the configured provider, and email login codes.
 *
 * When single sign-on is the only method, guests go straight to it, except in CI,
 * where the page always renders so end-to-end tests can use email login codes.
 *
 * In the local environment it also offers "Sign in as" for the seeded demo users, so a
 * local environment works without SSO, email or any credentials.
 */
class Login extends SimplePage
{
    use HasSiteHeader;

    private const string AUTH_DOCS_URL = 'https://laravel-starter.entapp.northwestern.edu/getting-started/installation/#5-environment-configuration';

    protected static ?string $title = 'Sign In';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect('/');

            return;
        }

        $signIn = resolve(SignIn::class);
        $ssoUrl = $signIn->url(SignInMethod::Sso);

        if ($ssoUrl !== null && $signIn->methods() === [SignInMethod::Sso] && ! App::environment('ci')) {
            $this->redirect($ssoUrl);
        }
    }

    public function content(Schema $schema): Schema
    {
        $signIn = resolve(SignIn::class);
        $ssoUrl = $signIn->url(SignInMethod::Sso);
        $emailUrl = $signIn->url(SignInMethod::EmailCode);
        $localAuthEnabled = $emailUrl !== null;
        $signInAs = $this->signInAsActions($signIn);

        return $schema
            ->components([
                Group::make([
                    Actions::make([
                        Action::make('netid')
                            ->label('Sign In with NetID')
                            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                            ->url($ssoUrl)
                            ->extraAttributes(['data-testid' => 'netid-login']),
                    ])->fullWidth(),
                    Text::make('For students, faculty, staff, and affiliates.')
                        ->extraAttributes(['class' => 'nu-sign-in-hint']),
                ])->dense()->visible($ssoUrl !== null),

                Text::make('or')
                    ->extraAttributes(['class' => 'nu-sign-in-divider'])
                    ->visible($ssoUrl !== null && $localAuthEnabled),

                Group::make([
                    Actions::make([
                        Action::make('email')
                            ->label('Sign In with Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->color('gray')
                            ->outlined()
                            ->url($emailUrl)
                            ->extraAttributes(['data-testid' => 'email-login']),
                    ])->fullWidth(),
                    Text::make('For approved external partners without a NetID.')
                        ->extraAttributes(['class' => 'nu-sign-in-hint']),
                ])->dense()->visible($localAuthEnabled),

                Text::make('Development')
                    ->extraAttributes(['class' => 'nu-sign-in-divider'])
                    ->visible($signInAs !== [] && ($ssoUrl !== null || $localAuthEnabled)),

                Group::make([
                    // One button however many users an application seeds, so it stays secondary to the real methods.
                    Actions::make([
                        ActionGroup::make($signInAs)
                            ->label('Sign In As…')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->color('gray')
                            ->outlined()
                            ->button()
                            ->dropdownPlacement('bottom')
                            ->dropdownWidth(Width::Small)
                            ->extraAttributes(['data-testid' => 'sign-in-as']),
                    ])->extraAttributes(['class' => 'nu-sign-in-as']),
                    Text::make('Sign in as a seeded user. Only in local environments.')
                        ->extraAttributes(['class' => 'nu-sign-in-hint']),
                ])->dense()->visible($signInAs !== []),

                // No callout heading: Filament renders it as an <h4>, which would skip levels after the page's <h1>.
                Callout::make()
                    ->description(new HtmlString(
                        '<strong>No sign-in methods are set up.</strong> Configure NetID sign-in or email codes so people can sign in.'
                    ))
                    ->warning()
                    ->actions([
                        Action::make('docs')
                            ->label('Authentication Documentation')
                            ->url(self::AUTH_DOCS_URL, shouldOpenInNewTab: true)
                            ->link(),
                    ])
                    ->visible($ssoUrl === null && ! $localAuthEnabled && $signInAs === []),
            ]);
    }

    /**
     * One menu item per seeded demo user that exists: the user's name, with their highest role as a badge.
     *
     * @return list<Action>
     */
    private function signInAsActions(SignIn $signIn): array
    {
        if (! $signIn->offers(SignInMethod::SignInAs)) {
            return [];
        }

        $order = array_flip(DemoUserSeeder::SIGN_IN_AS);

        return User::query()
            ->whereIn('username', DemoUserSeeder::SIGN_IN_AS)
            ->where('auth_type', '!=', AuthType::API)
            ->with('roles')
            ->get()
            ->sortBy(fn (User $user): int => $order[$user->username])
            ->map(fn (User $user): Action => Action::make('sign-in-as-' . Str::slug($user->username))
                ->label($user->full_name)
                ->badge($this->signInAsRole($user))
                ->badgeColor('gray')
                ->icon(Heroicon::OutlinedUser)
                ->url(route('filament.app.auth.login-as', ['username' => $user->username]))
                ->extraAttributes(['data-testid' => 'sign-in-as-' . $user->username]))
            ->values()
            ->all();
    }

    private function signInAsRole(User $user): string
    {
        $role = $user->non_default_roles->first()?->name;

        return $role ?? ($user->is_local_user ? 'Local Account' : 'Northwestern User');
    }
}
