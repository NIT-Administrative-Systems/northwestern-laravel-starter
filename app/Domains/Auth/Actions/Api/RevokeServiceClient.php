<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions\Api;

use App\Domains\Auth\Models\OAuthClient;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

/**
 * Revokes a service client and every access token it holds, so the integration loses
 * access at once rather than when its current token expires.
 */
readonly class RevokeServiceClient
{
    public function __construct(
        private AuditServiceClientChange $auditChange,
    ) {
    }

    public function __invoke(OAuthClient $client): void
    {
        DB::transaction(function () use ($client): void {
            Passport::token()->newQuery()
                ->where('client_id', $client->getKey())
                ->where('revoked', false)
                ->update(['revoked' => true]);

            $client->forceFill(['revoked' => true])->save();

            ($this->auditChange)($client, 'service_client_revoked');
        });
    }
}
