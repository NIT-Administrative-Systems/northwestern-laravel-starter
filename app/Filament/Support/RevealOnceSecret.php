<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\CodeEntry;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Phiki\Grammar\Grammar;

/**
 * A wizard that issues a credential and shows its secret once: a step that creates it, then a
 * step to copy it. The secret is kept encrypted in the session between the steps, never in
 * Livewire state, and never shown again:
 *
 * - each run of the wizard gets its own key when it mounts, kept in the mounted action's
 *   arguments, so a run never sees another's secret, even one open in another browser tab;
 * - it's issued once per run, so stepping back and forward again doesn't create a second one;
 * - a run that was cancelled or closed leaves nothing for the next one, on any record, to show;
 * - confirming the copy forgets it.
 *
 * Every method takes the Livewire component, which holds the mounted action and so the run.
 *
 * ```php
 * $secret = RevealOnceSecret::for('service_client:create');
 *
 * Action::make('createClient')
 *     ->mountUsing($secret->mountFresh())
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
     * Issue the credential, unless this run already did. `$issue` creates it and returns its ID,
     * its secret (null for a credential without one, such as a public application), and anything
     * else the wizard needs once it ends. With a `$scope`, the secret shows only on that record.
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
     * Whether this run issued its credential for `$record`, such as the client being rotated.
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

    public function identifierEntry(string $name, string $label): CodeEntry
    {
        return $this->entry($name, $label, fn (HasActions $livewire, ?Model $record): ?string => $this->identifier($livewire, $record));
    }

    /**
     * Shown only when the credential has a secret.
     */
    public function secretEntry(string $name, string $label): CodeEntry
    {
        return $this->entry($name, $label, fn (HasActions $livewire, ?Model $record): ?string => $this->secret($livewire, $record))
            ->visible(fn (HasActions $livewire, ?Model $record): bool => $this->secret($livewire, $record) !== null);
    }

    /**
     * @param  Closure(HasActions, ?Model): ?string  $state
     */
    private function entry(string $name, string $label, Closure $state): CodeEntry
    {
        return CodeEntry::make($name)
            ->label($label)
            ->grammar(Grammar::Txt)
            ->state(fn (HasActions $livewire, ?Model $record): ?string => $state($livewire, $record))
            ->dehydrated(false)
            ->copyable();
    }

    /**
     * What this run kept, for a credential issued for `$record` or for no record in particular.
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
     * The session key of the run in progress: the innermost mounted action that `mountFresh()`
     * gave a key. Read from the component's state rather than by resolving the action, so it's
     * safe while Filament is still resolving it, as when it checks the action's visibility.
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
