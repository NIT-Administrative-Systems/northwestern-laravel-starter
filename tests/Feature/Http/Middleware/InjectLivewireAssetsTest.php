<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\InjectLivewireAssets;
use Illuminate\Http\Request;
use Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InjectLivewireAssets::class)]
final class InjectLivewireAssetsTest extends TestCase
{
    public function test_it_calls_livewire_force_asset_injection(): void
    {
        Livewire::spy();

        // Call the middleware directly: a full request would also run Livewire's
        // response-time asset injection against the spy.
        $response = (new InjectLivewireAssets())->handle(Request::create('/'), fn () => response('OK'));

        $this->assertSame('OK', $response->getContent());

        /** @phpstan-ignore-next-line  */
        Livewire::shouldHaveReceived('forceAssetInjection')->once();
    }
}
