<?php

declare(strict_types=1);

namespace App\Domains\Api\ValueObjects;

use App\Domains\Api\Enums\AccessRefusal;
use App\Domains\Api\Enums\CredentialKind;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * What {@see \App\Domains\Api\CredentialAccess} decided, and why it refused. Callers act on
 * the reason the same way: a feature that is off is hidden and answers 404; anything else is
 * hidden or disabled and answers 403.
 */
final readonly class AccessDecision
{
    private function __construct(
        public bool $allowed,
        public ?AccessRefusal $reason,
        public CredentialKind $kind,
    ) {
        //
    }

    public static function allow(CredentialKind $kind): self
    {
        return new self(true, null, $kind);
    }

    public static function refuse(AccessRefusal $reason, CredentialKind $kind): self
    {
        return new self(false, $reason, $kind);
    }

    /**
     * Throws when refused: as a 404 when the feature is off, a 403 otherwise.
     *
     * @throws AuthorizationException
     */
    public function authorize(): void
    {
        if ($this->allowed) {
            return;
        }

        $exception = new AuthorizationException($this->message());

        throw $this->reason === AccessRefusal::FeatureOff ? $exception->asNotFound() : $exception;
    }

    /**
     * Why it was refused, in a sentence; empty when allowed.
     */
    public function message(): string
    {
        $kinds = $this->kind->plural();

        return match ($this->reason) {
            null => '',
            AccessRefusal::Unsupported => "That can't be done with {$kinds}.",
            AccessRefusal::FeatureOff => ucfirst($kinds) . ' are turned off.',
            AccessRefusal::Impersonating => ucfirst($kinds) . " can't be changed while impersonating someone.",
            AccessRefusal::MissingPermission => "You don't have permission to do that with {$kinds}.",
            AccessRefusal::AccountInactive => "The account is deactivated, so it can't use {$kinds}.",
            AccessRefusal::WrongAccountType => "This kind of account can't hold {$kinds}.",
        };
    }
}
