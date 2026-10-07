<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api;

use App\Domains\Api\ApiScopes;
use App\Domains\Auth\Enums\SystemPermission;
use Laravel\Mcp\Server\Registrar;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

#[CoversClass(ApiScopes::class)]
final class ApiScopesTest extends TestCase
{
    public function test_each_api_relevant_permission_is_a_rest_scope(): void
    {
        $apiRelevant = array_filter(SystemPermission::cases(), fn (SystemPermission $permission): bool => $permission->isApiRelevant());

        $this->assertSame(
            array_column(array_map(fn (SystemPermission $permission): array => [$permission->value, $permission->description()], $apiRelevant), 1, 0),
            ApiScopes::rest(),
        );
        $this->assertArrayNotHasKey(SystemPermission::ManageAll->value, ApiScopes::rest());
    }

    // MCP tokens and REST tokens can't stand in for each other.
    public function test_the_mcp_scope_is_issued_but_is_not_a_rest_scope(): void
    {
        $this->assertArrayHasKey(Registrar::OAUTH_SCOPE, ApiScopes::all());
        $this->assertArrayNotHasKey(Registrar::OAUTH_SCOPE, ApiScopes::rest());
        $this->assertSame([...ApiScopes::rest(), Registrar::OAUTH_SCOPE => ApiScopes::all()[Registrar::OAUTH_SCOPE]], ApiScopes::all());
    }

    public function test_scopes_are_named_after_their_permission(): void
    {
        $this->assertSame('View Users', ApiScopes::label(SystemPermission::ViewUsers->value));
        $this->assertSame('Use Tools', ApiScopes::label(Registrar::OAUTH_SCOPE));
        $this->assertSame('unknown-scope', ApiScopes::label('unknown-scope'));
    }

    // OpenAPI attributes can't call code, so BaseApiController lists the scopes itself.
    public function test_the_published_api_schema_lists_the_rest_scopes(): void
    {
        $schema = Yaml::parseFile(base_path('docs/schemas/api-schema.yaml'));

        foreach ($schema['components']['securitySchemes']['oauth2']['flows'] as $flow => $settings) {
            $this->assertSame(ApiScopes::rest(), $settings['scopes'], "The {$flow} flow's scopes don't match ApiScopes::rest().");
        }
    }
}
