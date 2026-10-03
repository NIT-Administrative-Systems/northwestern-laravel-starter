<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Pages;

use App\Domains\User\Models\User;
use App\Filament\App\Pages\ComponentGallery;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ComponentGallery::class)]
final class ComponentGalleryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(AppPanelProvider::ID);
    }

    public function test_page_renders_outside_production(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app/gallery')
            ->assertOk()
            ->assertSee('Starter placeholder')
            ->assertSee('Buttons')
            ->assertSee('Sample record one');
    }

    public function test_page_is_unavailable_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs(User::factory()->create())
            ->get('/app/gallery')
            ->assertForbidden();
    }

    public function test_the_sample_form_validates_and_saves_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(ComponentGallery::class)
            ->call('submitExample')
            ->assertHasErrors(['data.name' => 'required']);

        Livewire::test(ComponentGallery::class)
            ->fillForm(['name' => 'Willie the Wildcat'])
            ->call('submitExample')
            ->assertHasNoErrors()
            ->assertNotified('Form submitted');
    }
}
