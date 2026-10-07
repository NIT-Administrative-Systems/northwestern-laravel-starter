<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Api\Passport;

use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Api\Actions\Applications\RegisterOAuthApplication;
use App\Domains\Api\Passport\OAuthConsent;
use App\Domains\User\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The consent screen, its approval and the tokens it leads to are tested through the whole flow
 * in AuthorizationCodeFlowTest and McpFlowTest; this is the scope rule they share.
 */
#[CoversClass(OAuthConsent::class)]
final class OAuthConsentTest extends TestCase
{
    /**
     * @return \Iterator<string, array{list<string>, bool, list<string>}>
     */
    public static function grantableProvider(): \Iterator
    {
        yield 'the client may have it and the person holds it' => [['view-users'], true, ['view-users']];
        yield 'the client may not have it' => [[], true, []];
        yield 'the person does not hold it' => [['view-users'], false, []];
    }

    /**
     * @param  list<string>  $clientScopes
     * @param  list<string>  $expected
     */
    #[DataProvider('grantableProvider')]
    public function test_a_connection_gets_the_scopes_the_client_may_have_and_the_person_holds(array $clientScopes, bool $personHoldsThem, array $expected): void
    {
        $person = User::factory()->affiliate()->create();

        if ($personHoldsThem) {
            $person->givePermissionTo(SystemPermission::ViewUsers);
        }

        [, $client] = resolve(RegisterOAuthApplication::class)('Reporting Tool', ['https://reports.example.edu/callback'], false, $clientScopes);

        $this->assertSame($expected, resolve(OAuthConsent::class)->grantableScopes($person, $client));
    }
}
