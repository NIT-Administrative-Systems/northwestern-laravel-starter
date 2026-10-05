<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Applications;

use App\Domains\Auth\Models\OAuthClient;
use InvalidArgumentException;
use Laravel\Passport\ClientRepository;

/**
 * Replaces a confidential application's client secret. The old secret stops working at once,
 * so the application must be updated with the new one.
 */
readonly class RegenerateOAuthApplicationSecret
{
    public function __construct(
        private ClientRepository $clients,
    ) {
    }

    /**
     * @return non-empty-string The new secret, shown once
     */
    public function __invoke(OAuthClient $client): string
    {
        if (! $client->confidential()) {
            throw new InvalidArgumentException('A public application has no client secret.');
        }

        $this->clients->regenerateSecret($client);

        /** @var non-empty-string $secret */
        $secret = $client->plainSecret;

        return $secret;
    }
}
