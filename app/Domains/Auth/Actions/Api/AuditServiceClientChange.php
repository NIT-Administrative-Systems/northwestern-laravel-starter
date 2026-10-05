<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Api;

use App\Domains\Auth\Models\OAuthClient;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Records a change to a service client as a custom audit event on the API user that owns it,
 * so the API user's audit history shows who created, rotated, restricted or revoked its
 * clients. The client itself isn't audited: its UUID key doesn't fit the audits table, and
 * its secret must never be recorded.
 */
readonly class AuditServiceClientChange
{
    /**
     * @param  'service_client_created'|'service_client_revoked'|'service_client_ip_restrictions_updated'  $event
     * @param  array<string, mixed>  $old
     */
    public function __invoke(OAuthClient $client, string $event, array $old = []): void
    {
        $apiUser = $client->owner;

        if (! $apiUser instanceof User) {
            return;
        }

        $apiUser->auditEvent = $event;
        $apiUser->isCustomEvent = true;
        $apiUser->auditCustomOld = $old;
        $apiUser->auditCustomNew = [
            'client_id' => $client->getKey(),
            'name' => $client->name,
            'secret_expires_at' => $client->secret_expires_at?->toIso8601String(),
            'allowed_ips' => $client->allowed_ips,
            'revoked' => (bool) $client->getAttributes()['revoked'],
            'rotated_from_client_id' => $client->getAttribute('rotated_from_client_id'),
        ];

        Event::dispatch(new AuditCustom($apiUser));
    }
}
