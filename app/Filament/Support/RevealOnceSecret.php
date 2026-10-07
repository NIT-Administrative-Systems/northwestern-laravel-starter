<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Infolists\Components\CodeEntry;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Phiki\Grammar\Grammar;

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

    public function identifierEntry(string $name, string $label): CodeEntry
    {
        return $this->entry($name, $label, fn (?Model $record): ?string => $this->identifier($record));
    }

    /**
     * Shown only when the credential has a secret.
     */
    public function secretEntry(string $name, string $label): CodeEntry
    {
        return $this->entry($name, $label, fn (?Model $record): ?string => $this->secret($record))
            ->visible(fn (?Model $record): bool => $this->secret($record) !== null);
    }

    /**
     * @param  Closure(?Model): ?string  $state
     */
    private function entry(string $name, string $label, Closure $state): CodeEntry
    {
        return CodeEntry::make($name)
            ->label($label)
            ->grammar(Grammar::Txt)
            ->state(fn (?Model $record): ?string => $state($record))
            ->dehydrated(false)
            ->copyable();
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
