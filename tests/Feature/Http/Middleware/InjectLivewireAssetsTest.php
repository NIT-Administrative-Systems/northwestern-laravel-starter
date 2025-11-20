<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\InjectLivewireAssets;
use Illuminate\Support\Facades\Route;
use Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InjectLivewireAssets::class)]
class InjectLivewireAssetsTest extends TestCase
{
    public function test_it_calls_livewire_force_asset_injection(): void
    {
        Livewire::spy();

        Route::middleware(InjectLivewireAssets::class)
            ->get('/test-livewire-middleware', fn () => response('OK'));

        $response = $this->get('/test-livewire-middleware');

        $response->assertOk();
        $response->assertSee('OK');

        /** @phpstan-ignore-next-line  */
        Livewire::shouldHaveReceived('forceAssetInjection')->once();
    }
}
