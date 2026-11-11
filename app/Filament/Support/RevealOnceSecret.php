<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
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

/**
 * A wizard that issues a credential and shows its secret once: a step that creates it, then a
 * step to copy it. The secret is kept encrypted in the session between the steps, never in
 * Livewire state, and never shown again:
 *
 * - it's issued once per run, so stepping back and forward again doesn't create a second one;
 * - a run that was cancelled or closed leaves nothing for the next one, on any record, to show;
 * - confirming the copy forgets it.
 *
 * ```php
 * $secret = RevealOnceSecret::for('service_client:create');
 *
 * Action::make('createClient')
 *     ->mountUsing($secret->mountFresh())
 *     ->modifyWizardUsing($secret->hideBackOnceIssued())
 *     ->modalSubmitAction($secret->savedAction())
 *     ->steps([
 *         Step::make('Configure')->afterValidation(fn (array $state) => $secret->issueOnce(function () use ($state): array {
 *             [$plain, $client] = $createClient($state);
 *
 *             return ['id' => $client->getKey(), 'secret' => $plain];
 *         })),
 *         Step::make('Copy')->schema([$secret->identifierEntry('client_id', 'Client ID'), $secret->secretEntry('client_secret', 'Client Secret')]),
 *     ])
 *     ->action(fn () => $secret->forget());
 * ```
 */
final readonly class RevealOnceSecret
{
    private function __construct(
        private string $sessionKey,
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
     * For the wizard's `mountUsing()`: forget anything an earlier run left, and fill the form.
     */
    public function mountFresh(): Closure
    {
        return function (?Schema $schema): void {
            $this->forget();
            $schema?->fill();
        };
    }

    /**
     * Issue the credential, unless this run already did. `$issue` creates it and returns its ID,
     * its secret (null for a credential without one, such as a public application), and anything
     * else the wizard needs once it ends. With a `$scope`, the secret shows only on that record.
     *
     * @param  Closure(): array<string, mixed>  $issue  Returns at least `id` and `secret`
     */
    public function issueOnce(Closure $issue, ?Model $scope = null): void
    {
        if ($this->issued()) {
            return;
        }

        $issued = $issue();
        $secret = $issued['secret'] ?? null;

        Session::put($this->sessionKey, [
            ...$issued,
            'id' => (string) $issued['id'],
            'secret' => is_string($secret) ? Crypt::encryptString($secret) : null,
            'scope' => $scope?->getKey(),
        ]);
    }

    public function issued(): bool
    {
        return Session::has($this->sessionKey);
    }

    /**
     * Whether this run issued its credential for `$record`, such as the client being rotated.
     */
    public function isFor(Model $record): bool
    {
        $stored = $this->stored();

        return $stored !== null && $stored['scope'] !== null && (string) $stored['scope'] === (string) $record->getKey();
    }

    public function identifier(?Model $record = null): ?string
    {
        $stored = $this->storedFor($record);

        return is_string($stored['id'] ?? null) ? $stored['id'] : null;
    }

    public function secret(?Model $record = null): ?string
    {
        $stored = $this->storedFor($record);

        return is_string($stored['secret'] ?? null) ? Crypt::decryptString($stored['secret']) : null;
    }

    /**
     * Something else `issueOnce()` kept, such as the ID of the API user it created.
     */
    public function get(string $key): mixed
    {
        return $this->stored()[$key] ?? null;
    }

    public function forget(): void
    {
        Session::forget($this->sessionKey);
    }

    /**
     * For the wizard's `modifyWizardUsing()`: once the credential is issued, going back would only
     * show a form for one that already exists.
     */
    public function hideBackOnceIssued(): Closure
    {
        return fn (Wizard $wizard): Wizard => $wizard->previousAction(
            // The wizard renders its Back button even when hidden, so the class takes it off the page.
            fn (Action $action): Action => $action
                ->hidden(fn (): bool => $this->issued())
                ->extraAttributes(fn (): array => $this->issued() ? ['class' => 'hidden'] : []),
        );
    }

    /**
     * For the wizard's `modalSubmitAction()`: the last button confirms the secret was saved.
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
        return $this->entry($name, $label, fn (?Model $record): ?string => $this->identifier($record));
    }

    /**
     * Shown only when the credential has a secret.
     */
    public function secretEntry(string $name, string $label): TextEntry
    {
        return $this->entry($name, $label, fn (?Model $record): ?string => $this->secret($record))
            ->visible(fn (?Model $record): bool => $this->secret($record) !== null);
    }

    /**
     * The value in full, wrapped rather than scrolled, with a Copy button under it.
     *
     * @param  Closure(?Model): ?string  $state
     */
    private function entry(string $name, string $label, Closure $state): TextEntry
    {
        return TextEntry::make($name)
            ->label($label)
            ->state(fn (?Model $record): ?string => $state($record))
            ->fontFamily(FontFamily::Mono)
            ->extraAttributes(['class' => 'break-all'])
            ->belowContent(fn (?Model $record): HtmlString => self::copyButton((string) $state($record), $label))
            ->dehydrated(false);
    }

    private static function copyButton(string $value, string $label): HtmlString
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
     * What this run kept, for a credential issued for `$record` or for no record in particular.
     *
     * @return array<string, mixed>|null
     */
    private function storedFor(?Model $record): ?array
    {
        $stored = $this->stored();

        if ($stored === null || ($stored['scope'] !== null && (! $record instanceof Model || ! $this->isFor($record)))) {
            return null;
        }

        return $stored;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stored(): ?array
    {
        $stored = Session::get($this->sessionKey);

        return is_array($stored) ? $stored : null;
    }
}
