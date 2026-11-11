<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use Laravel\Telescope\Telescope;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
final class TelescopeServiceProviderTest extends TestCase
{
    // A failed OAuth request is recorded outside local, so its secrets must not be.
    public function test_oauth_secrets_are_hidden_from_recorded_requests(): void
    {
        foreach (['_token', 'client_secret', 'refresh_token', 'code', 'code_verifier', 'secret'] as $parameter) {
            $this->assertContains($parameter, Telescope::$hiddenRequestParameters);
        }
    }
}
