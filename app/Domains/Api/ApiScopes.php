<?php

declare(strict_types=1);

namespace App\Domains\Api;

use App\Domains\Auth\Enums\SystemPermission;
use Laravel\Mcp\Server\Registrar;

/**
 * Every OAuth scope the application issues, and how each is named in the interface.
 *
 * The REST API has one scope per API-relevant permission, named after it: a token can do what
 * its scopes name, and no more than its user's permissions allow. MCP clients get only
 * `mcp:use`, which no REST route accepts, so their tokens and REST tokens can't stand in for
 * each other. Mark a permission API-relevant ({@see SystemPermission::isApiRelevant()}) to add a
 * scope, and add it to the OpenAPI security schemes in BaseApiController, which
 * ApiScopesTest checks against this list.
 */
final class ApiScopes
{
    /**
     * The REST API's scopes, with what each lets a token do.
     *
     * @return array<string, string>
     */
    public static function rest(): array
    {
        return collect(SystemPermission::cases())
            ->filter(fn (SystemPermission $permission): bool => $permission->isApiRelevant())
            ->mapWithKeys(fn (SystemPermission $permission): array => [$permission->value => $permission->description()])
            ->all();
    }

    /**
     * Every scope Passport may issue: the REST API's and `mcp:use`.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [...self::rest(), Registrar::OAUTH_SCOPE => "Use this application's tools from an AI client"];
    }

    /**
     * A scope's name in the interface: the permission it's named after ("View Users"), or "Use
     * Tools" for the MCP scope.
     */
    public static function label(string $scope): string
    {
        return $scope === Registrar::OAUTH_SCOPE ? 'Use Tools' : (SystemPermission::tryFrom($scope)?->getLabel() ?? $scope);
    }
}
