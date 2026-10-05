<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Api;

use App\Domains\Auth\Models\OAuthClient;

/**
 * Replaces a service client's IP allowlist. An empty list allows every IP address.
 */
readonly class UpdateServiceClientIpRestrictions
{
    public function __construct(
        private AuditServiceClientChange $auditChange,
    ) {
    }

    /**
     * @param  list<non-empty-string>|null  $allowedIps
     */
    public function __invoke(OAuthClient $client, ?array $allowedIps): void
    {
        $previous = $client->allowed_ips;

        $client->forceFill(['allowed_ips' => filled($allowedIps) ? array_values($allowedIps) : null])->save();

        ($this->auditChange)($client, 'service_client_ip_restrictions_updated', ['allowed_ips' => $previous]);
    }
}
