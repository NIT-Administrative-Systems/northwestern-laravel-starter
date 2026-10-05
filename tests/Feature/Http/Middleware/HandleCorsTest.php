<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\HandleCors;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(HandleCors::class)]
final class HandleCorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mcp.enabled' => true, 'cors.allowed_origins' => ['https://dashboard.example.edu']]);
    }

    // Browser-based MCP clients call the server and the OAuth endpoints from their own origin.
    public function test_any_origin_may_call_the_mcp_and_oauth_endpoints_without_credentials(): void
    {
        foreach (['/mcp', '/oauth/token', '/oauth/register', '/.well-known/oauth-protected-resource/mcp'] as $path) {
            $this->preflight($path, 'https://inspector.example.com')
                ->assertNoContent()
                ->assertHeader('Access-Control-Allow-Origin', '*')
                ->assertHeaderMissing('Access-Control-Allow-Credentials');
        }

        $this->getJson('/.well-known/oauth-authorization-server', ['Origin' => 'https://inspector.example.com'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_the_rest_api_allows_only_its_configured_origins(): void
    {
        $this->preflight('/api/v1/me', 'https://dashboard.example.edu')->assertHeader('Access-Control-Allow-Origin', 'https://dashboard.example.edu');
        $this->assertNotContains(
            $this->preflight('/api/v1/me', 'https://inspector.example.com')->headers->get('Access-Control-Allow-Origin'),
            ['https://inspector.example.com', '*'],
        );
    }

    /**
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function preflight(string $path, string $origin): \Illuminate\Testing\TestResponse
    {
        return $this->call('OPTIONS', $path, server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);
    }
}
