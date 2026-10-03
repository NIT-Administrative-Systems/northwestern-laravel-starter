<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Auth;

use App\Domains\Auth\Actions\Local\AuthenticateWithLoginCode;
use App\Domains\Auth\Actions\Local\RequestLoginCode;
use App\Domains\Auth\ValueObjects\LoginCodeSession;
use App\Filament\App\Pages\Concerns\HasSiteHeader;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Passwordless sign-in for local (non-NetID) users, in two steps on one page:
 * request a code by email, then enter it.
 *
 * Progress lives in the session ({@see LoginCodeSession}), so reloading the page
 * returns to the step the user was on.
 *
 * @property-read Schema $form
 * @property-read Schema $codeForm
 */
class EmailCodeLogin extends SimplePage
{
    use HasSiteHeader;

    protected static ?string $title = 'Sign in with email';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $codeData = [];

    /** The email a code was requested for; set once the code step begins. */
    #[Locked]
    public ?string $email = null;

    public function mount(): void
    {
        abort_unless(config('local-auth.enabled'), 404);

        if (Filament::auth()->check()) {
            $this->redirect('/');

            return;
        }

        $this->email = LoginCodeSession::email();

        $this->form->fill();
        $this->codeForm->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->autocomplete('email')
                    ->autofocus()
                    ->extraInputAttributes(['data-cy' => 'email-input']),
            ])
            ->statePath('data');
    }

    public function codeForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                OneTimeCodeInput::make('code')
                    ->label('Verification code')
                    ->length((int) config('local-auth.code.digits', 6))
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes(['data-cy' => 'code-input'])
                    ->belowContent(
                        Action::make('resendCode')
                            ->label('Resend code')
                            ->link()
                            ->action(fn () => $this->resendCode())
                    ),
            ])
            ->statePath('codeData');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('requestCode')
                    ->footer([
                        Actions::make([
                            Action::make('requestCode')
                                ->label('Continue')
                                ->submit('requestCode')
                                ->extraAttributes(['data-cy' => 'continue-button']),
                        ])->fullWidth(),
                    ])
                    ->visible(fn (): bool => blank($this->email)),

                Form::make([EmbeddedSchema::make('codeForm')])
                    ->id('codeForm')
                    ->livewireSubmitHandler('verifyCode')
                    ->footer([
                        Actions::make([
                            Action::make('verifyCode')
                                ->label('Verify')
                                ->submit('verifyCode')
                                ->extraAttributes(['data-cy' => 'verify-button']),
                        ])->fullWidth(),
                    ])
                    ->visible(fn (): bool => filled($this->email)),

                Actions::make([
                    Action::make('backToSignIn')
                        ->label('Back to sign-in options')
                        ->icon(Heroicon::OutlinedArrowLeft)
                        ->link()
                        ->url(fn (): ?string => Filament::getLoginUrl())
                        ->visible(fn (): bool => blank($this->email)),
                    Action::make('useDifferentEmail')
                        ->label('Use a different email')
                        ->icon(Heroicon::OutlinedArrowLeft)
                        ->link()
                        ->action(fn () => $this->useDifferentEmail())
                        ->visible(fn (): bool => filled($this->email)),
                ])->alignCenter(),
            ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return blank($this->email) ? 'Request a verification code' : 'Check your email';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (blank($this->email)) {
            return 'Enter the email address associated with your account to receive a verification code.';
        }

        return new HtmlString('We sent an email to <strong>' . e($this->email) . '</strong>. Enter the verification code below to sign in.');
    }

    public function requestCode(): void
    {
        abort_unless(config('local-auth.enabled'), 404);

        $email = mb_strtolower(trim((string) $this->form->getState()['email']));

        try {
            $challenge = resolve(RequestLoginCode::class)($email, request()->ip(), request()->userAgent());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.email' => $e->errors()['email'] ?? []]);
        }

        LoginCodeSession::start($email, $challenge);

        $this->email = $email;
        $this->codeForm->fill();
    }

    public function verifyCode(): RedirectResponse|Redirector
    {
        abort_unless(config('local-auth.enabled'), 404);

        $code = (string) $this->codeForm->getState()['code'];

        try {
            $user = resolve(AuthenticateWithLoginCode::class)(LoginCodeSession::challengeId(), $code, request());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['codeData.code' => $e->errors()['code'] ?? []]);
        }

        Auth::login($user, remember: true);
        Session::regenerate();
        Session::regenerateToken();
        LoginCodeSession::forget();

        // Every sign-in path lands on `/`, where HomeController decides where signed-in users go.
        return redirect()->intended('/');
    }

    public function resendCode(): void
    {
        $email = LoginCodeSession::email();

        if ($email === null) {
            $this->useDifferentEmail();

            return;
        }

        $cooldownKey = "login-code-resend:{$email}";

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            Notification::make()
                ->title('Please wait ' . RateLimiter::availableIn($cooldownKey) . ' seconds before requesting another code.')
                ->warning()
                ->send();

            return;
        }

        try {
            $challenge = resolve(RequestLoginCode::class)($email, request()->ip(), request()->userAgent());
        } catch (ValidationException $e) {
            Notification::make()
                ->title($e->errors()['email'][0] ?? 'Unable to resend the code.')
                ->danger()
                ->send();

            return;
        }

        LoginCodeSession::replaceChallenge($challenge);

        RateLimiter::hit($cooldownKey, (int) config('local-auth.code.resend_cooldown_seconds', 30));

        Notification::make()
            ->title('Verification code resent.')
            ->success()
            ->send();
    }

    public function useDifferentEmail(): void
    {
        LoginCodeSession::forget();

        $this->email = null;
        $this->form->fill();
        $this->codeForm->fill();
    }
}
