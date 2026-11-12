<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A wizard that issues a credential and shows
 * its secret once: one step creates it, and
 * the next shows the secret for copying.
 *
 * Between the steps the secret is kept encrypted in the session
 * rather than in Livewire state, and it's never shown again.
 *
 * Each run of the wizard gets its own key when it mounts,
 * which the mounted action's arguments keep, so no run
 * can see another's secret, even one in another tab.
 *
 * A run issues its credential once, so stepping back and
 * forward doesn't create a second one. A cancelled or
 * closed run leaves nothing for the next to show,
 * and confirming the copy forgets the secret.
 *
 * Every method takes the Livewire component, which
 * holds the mounted action and so holds the run.
 *
 * ```php
 * $secret = RevealOnceSecret::for('service_client:create');
 *
 * Action::make('createClient')
 *     ->mountUsing($secret->mountFresh())
 *     ->modifyWizardUsing($secret->hideBackOnceIssued())
 *     ->modalSubmitAction($secret->savedAction())
 *     ->steps([
 *         Step::make('Configure')->afterValidation(fn (array $state, HasActions $livewire) => $secret->issueOnce($livewire, function () use ($state): array {
 *             [$plain, $client] = $createClient($state);
 *
 *             return ['id' => $client->getKey(), 'secret' => $plain];
 *         })),
 *         Step::make('Copy')->schema([$secret->identifierEntry('client_id', 'Client ID'), $secret->secretEntry('client_secret', 'Client Secret')]),
 *     ])
 *     ->action(fn (HasActions $livewire) => $secret->forget($livewire));
 * ```
 */
final readonly class RevealOnceSecret
{
    /**
     * The mounted action's argument that holds the run's key.
     */
    public const string RUN_ARGUMENT = 'revealOnceSecretRun';

    private function __construct(
        private string $sessionKeyPrefix,
    ) {
        //
    }

    /**
     * @param  non-empty-string  $wizard  Names the wizard, so two wizards never see each other's secret
     */
    public static function for(string $wizard): self
    {
        return new self("reveal_once_secret:{$wizard}");
    }

    /**
     * For the wizard's `mountUsing()`: give this run its own key, and fill the form.
     */
    public function mountFresh(): Closure
    {
        return function (?Schema $schema, HasActions $livewire): void {
            $schema?->fill();
            $livewire->mergeMountedActionArguments([self::RUN_ARGUMENT => Str::random(40)]);
        };
    }

    /**
     * Issue the credential, unless this run already has. The $issue closure creates
     * it and returns its ID, its secret and anything else the wizard needs later.
     *
     * The secret is null for a credential without
     * one, such as a public application. With a
     * $scope, it shows only on that record.
     *
     * @param  Closure(): array<string, mixed>  $issue  Returns at least `id` and `secret`
     */
    public function issueOnce(HasActions $livewire, Closure $issue, ?Model $scope = null): void
    {
        $sessionKey = $this->sessionKey($livewire);

        if ($sessionKey === null || Session::has($sessionKey)) {
            return;
        }

        $issued = $issue();
        $secret = $issued['secret'] ?? null;

        Session::put($sessionKey, [
            ...$issued,
            'id' => (string) $issued['id'],
            'secret' => is_string($secret) ? Crypt::encryptString($secret) : null,
            'scope' => $scope?->getKey(),
        ]);
    }

    public function issued(HasActions $livewire): bool
    {
        return $this->stored($livewire) !== null;
    }

    /**
     * Determine whether this run issued its credential
     * for $record, such as the client being rotated.
     */
    public function isFor(HasActions $livewire, Model $record): bool
    {
        $stored = $this->stored($livewire);

        return $stored !== null && $stored['scope'] !== null && (string) $stored['scope'] === (string) $record->getKey();
    }

    public function identifier(HasActions $livewire, ?Model $record = null): ?string
    {
        $stored = $this->storedFor($livewire, $record);

        return is_string($stored['id'] ?? null) ? $stored['id'] : null;
    }

    public function secret(HasActions $livewire, ?Model $record = null): ?string
    {
        $stored = $this->storedFor($livewire, $record);

        return is_string($stored['secret'] ?? null) ? Crypt::decryptString($stored['secret']) : null;
    }

    /**
     * Something else `issueOnce()` kept, such as the ID of the API user it created.
     */
    public function get(HasActions $livewire, string $key): mixed
    {
        return $this->stored($livewire)[$key] ?? null;
    }

    public function forget(HasActions $livewire): void
    {
        $sessionKey = $this->sessionKey($livewire);

        if ($sessionKey !== null) {
            Session::forget($sessionKey);
        }
    }

    /**
     * For the wizard's modifyWizardUsing(): once the
     * credential is issued, going back would only
     * show a form for one that already exists.
     */
    public function hideBackOnceIssued(): Closure
    {
        return fn (Wizard $wizard): Wizard => $wizard->previousAction(
            // The wizard renders its Back button even when it's
            // hidden, so a CSS class takes it off the page.
            fn (Action $action): Action => $action
                ->hidden(fn (HasActions $livewire): bool => $this->issued($livewire))
                ->extraAttributes(fn (HasActions $livewire): array => $this->issued($livewire) ? ['class' => 'hidden'] : []),
        );
    }

    /**
     * For the wizard's submit action: the last button confirms the secret was saved.
     */
    public function savedAction(): Closure
    {
        return fn (Action $action): Action => $action
            ->label('I\'ve Saved It')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->iconPosition(IconPosition::After);
    }

    public function identifierEntry(string $name, string $label): TextEntry
    {
        return $this->entry($name, $label, fn (HasActions $livewire, ?Model $record): ?string => $this->identifier($livewire, $record));
    }

    /**
     * Shown only when the credential has a secret.
     */
    public function secretEntry(string $name, string $label): TextEntry
    {
        return $this->entry($name, $label, fn (HasActions $livewire, ?Model $record): ?string => $this->secret($livewire, $record))
            ->visible(fn (HasActions $livewire, ?Model $record): bool => $this->secret($livewire, $record) !== null);
    }

    /**
     * The value in full, wrapped rather than scrolled, with a Copy button under it.
     *
     * @param  Closure(HasActions, ?Model): ?string  $state
     */
    private function entry(string $name, string $label, Closure $state): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(fn (HasActions $livewire, ?Model $record): ?string => $state($livewire, $record))
            ->fontFamily(FontFamily::Mono)
            ->extraAttributes(['class' => 'break-all'])
            ->belowContent(fn (HasActions $livewire, ?Model $record): HtmlString => $this->copyButton((string) $state($livewire, $record), $label))
            ->dehydrated(false);
    }

    private function copyButton(string $value, string $label): HtmlString
    {
        return new HtmlString(Blade::render(<<<'BLADE'
            <div x-data="{ copied: false }">
                <x-filament::button color="gray"
                                    size="sm"
                                    :icon="\Filament\Support\Icons\Heroicon::OutlinedClipboardDocument"
                                    x-on:click="window.navigator.clipboard.writeText(@js($value)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
                    <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    <span class="sr-only">{{ $label }}</span>
                </x-filament::button>
                <span class="sr-only" aria-live="polite" x-text="copied ? @js($label . ' copied') : ''"></span>
            </div>
            BLADE, ['value' => $value, 'label' => $label]));
    }

    /**
     * Get what this run kept, for a credential issued
     * for $record or for no record in particular.
     *
     * @return array<string, mixed>|null
     */
    private function storedFor(HasActions $livewire, ?Model $record): ?array
    {
        $stored = $this->stored($livewire);

        if ($stored === null || ($stored['scope'] !== null && (! $record instanceof Model || ! $this->isFor($livewire, $record)))) {
            return null;
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored(HasActions $livewire): ?array
    {
        $sessionKey = $this->sessionKey($livewire);
        $stored = $sessionKey === null ? null : Session::get($sessionKey);

        return is_array($stored) ? $stored : null;
    }

    /**
     * Get the session key of the run in progress, from the innermost mounted
     * action that mountFresh() gave a key. It reads the component's state
     * instead of resolving the action, so it's safe while Filament is
     * still resolving it, as when it checks whether it's visible.
     */
    private function sessionKey(HasActions $livewire): ?string
    {
        $mountedActions = property_exists($livewire, 'mountedActions') && is_array($livewire->mountedActions) ? $livewire->mountedActions : [];

        foreach (array_reverse($mountedActions) as $mountedAction) {
            $run = is_array($mountedAction) && is_array($mountedAction['arguments'] ?? null) ? ($mountedAction['arguments'][self::RUN_ARGUMENT] ?? null) : null;

            if (is_string($run) && $run !== '') {
                return "{$this->sessionKeyPrefix}:{$run}";
            }
        }

        return null;
    }
}
