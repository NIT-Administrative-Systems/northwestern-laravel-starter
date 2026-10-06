<?php

declare(strict_types=1);

namespace App\Domains\Auth\Repositories;

use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

/**
 * Passport's client repository, for UUID client IDs. PostgreSQL refuses to compare a UUID
 * column with anything else, so a malformed `client_id` sent to `/oauth/token` failed with
 * a database error. It's an unknown client instead, and Passport answers `invalid_client`.
 */
class OAuthClientRepository extends ClientRepository
{
    public function find(string|int $id): ?Client
    {
        return Str::isUuid((string) $id) ? parent::find($id) : null;
    }
}
