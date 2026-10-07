<?php

declare(strict_types=1);

namespace App\Filament\App\Pages\Auth;

use App\Domains\Auth\Enums\SignInMethod;
use App\Domains\Auth\LoginCodes;
use App\Domains\Auth\SignIn;
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
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Northwestern\SysDev\Chassis\Formatting\CountInWords;

/**
 * Passwordless sign-in for local (non-NetID) users, in two steps on one page:
 * request a code by email, then enter it.
 *
 * {@see LoginCodes} does the work and keeps progress in the session, so reloading the page
 * returns to the step the user was on.
 *
 * @property-read Schema $form
 * @property-read Schema $codeForm
 */
class EmailCodeLogin extends SimplePage
{
    use HasSiteHeader;

    protected static ?string $title = 'Sign In with Email';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $codeData = [];

    /** The email a code was requested for; set once the code step begins. */
    #[Locked]
    public ?string $email = null;

    public function mount(): void
    {
        abort_unless(resolve(SignIn::class)->offers(SignInMethod::EmailCode), 404);

        if (Filament::auth()->check()) {
            $this->redirect('/');

            return;
        }

        $loginCodes = resolve(LoginCodes::class);
        $token = request()->query(LoginCodes::LINK_PARAMETER);

        if (is_string($token) && $token !== '') {
            $loginCodes->startFromLink($token);
        }

        $this->email = $loginCodes->pendingEmail();

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
                    ->extraInputAttributes(['data-testid' => 'email-input']),
            ])
            ->statePath('data');
    }

    public function codeForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                OneTimeCodeInput::make('code')
                    ->label('Verification Code')
                    ->length((int) config('local-auth.code.digits', 6))
                    ->extraFieldWrapperAttributes(['class' => 'nu-login-code'])
                    ->required()
                    ->autofocus()
                    ->extraInputAttributes(['data-testid' => 'code-input'])
                    ->belowContent(
                        Action::make('resendCode')
                            ->label('Resend Code')
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
                                ->extraAttributes(['data-testid' => 'continue-button']),
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
                                ->extraAttributes(['data-testid' => 'verify-button']),
                        ])->fullWidth(),
                    ])
                    ->visible(fn (): bool => filled($this->email)),

                Actions::make([
                    Action::make('backToSignIn')
                        ->label('Back to Sign-In Options')
                        ->icon(Heroicon::OutlinedArrowLeft)
                        ->link()
                        ->url(fn (): ?string => Filament::getLoginUrl())
                        ->visible(fn (): bool => blank($this->email)),
                    Action::make('useDifferentEmail')
                        ->label('Use a Different Email')
                        ->icon(Heroicon::OutlinedArrowLeft)
                        ->link()
                        ->action(fn () => $this->useDifferentEmail())
                        ->visible(fn (): bool => filled($this->email)),
                ])->alignCenter(),
            ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return blank($this->email) ? 'Request a Verification Code' : 'Check Your Email';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (blank($this->email)) {
            return 'Enter the email address on your account, and we\'ll send you a verification code.';
        }

        return new HtmlString('We sent an email to <strong>' . e($this->email) . '</strong>. Enter the verification code below to sign in.');
    }

    public function requestCode(): void
    {
        abort_unless(resolve(SignIn::class)->offers(SignInMethod::EmailCode), 404);

        $loginCodes = resolve(LoginCodes::class);

        $email = (string) $this->form->getState()['email'];

        try {
            $loginCodes->request($email, request());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['data.email' => $e->errors()['email'] ?? []]);
        }

        $this->email = $loginCodes->pendingEmail();
        $this->codeForm->fill();

        // The email form, and the button that had focus, are replaced by the code form. `autofocus`
        // works once per page, so move focus to the code's first digit, which also names the new step.
        $this->js("document.querySelector('.fi-one-time-code-input-digit')?.focus()");
    }

    public function verifyCode(): RedirectResponse
    {
        $signIn = resolve(SignIn::class);
        abort_unless($signIn->offers(SignInMethod::EmailCode), 404);

        $code = (string) $this->codeForm->getState()['code'];

        try {
            $user = resolve(LoginCodes::class)->verify($code, request());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['codeData.code' => $e->errors()['code'] ?? []]);
        }

        return $signIn->complete($user, request(), SignInMethod::EmailCode);
    }

    public function resendCode(): void
    {
        $loginCodes = resolve(LoginCodes::class);

        if ($loginCodes->pendingEmail() === null) {
            $this->useDifferentEmail();

            return;
        }

        if (($seconds = $loginCodes->resendAvailableIn()) > 0) {
            Notification::make()
                ->title('Wait a Moment')
                ->body('You can request another code in ' . CountInWords::of($seconds, 'second') . '.')
                ->warning()
                ->send();

            return;
        }

        try {
            $loginCodes->resend(request());
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Code Not Sent')
                ->body($e->errors()['email'][0] ?? 'We couldn\'t resend the code. Try again in a minute.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Code Sent')
            ->body('Check your email for the new code.')
            ->success()
            ->send();
    }

    public function useDifferentEmail(): void
    {
        resolve(LoginCodes::class)->cancel();

        $this->email = null;
        $this->form->fill();
        $this->codeForm->fill();

        $this->js("document.querySelector('input[type=\"email\"]')?.focus()");
    }
}
