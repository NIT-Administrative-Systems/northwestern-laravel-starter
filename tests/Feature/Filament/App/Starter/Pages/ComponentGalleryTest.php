<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\App\Starter\Pages;

use App\Domains\User\Models\User;
use App\Filament\App\Starter\Pages\ComponentGallery;
use App\Providers\Filament\AppPanelProvider;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing]
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

    // The app panel's dashboard is Filament's own, so the sidebar is the gallery's only way in.
    public function test_the_sidebar_links_to_the_gallery_outside_production(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/app')
            ->assertOk()
            ->assertSee('href="' . url('/app/gallery') . '"', escape: false);
    }

    public function test_the_sidebar_hides_the_gallery_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs(User::factory()->create())
            ->get('/app')
            ->assertOk()
            ->assertDontSee(url('/app/gallery'), escape: false);
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
            ->assertNotified('Form Submitted');
    }

    public function test_the_notification_example_sends_a_database_notification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ComponentGallery::class)
            ->call('sendSampleNotification')
            ->assertDispatched('databaseNotificationsSent');

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame('A Sample Notification', $user->notifications()->first()?->data['title']);
    }
}
