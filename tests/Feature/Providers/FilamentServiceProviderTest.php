<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\User\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

/**
 * Defaults FilamentServiceProvider gives every panel. The provider is outside the coverage gate.
 */
#[CoversNothing]
final class FilamentServiceProviderTest extends TestCase
{
    public function test_dates_and_times_show_in_the_persons_timezone(): void
    {
        $this->actingAs(User::factory()->create(['timezone' => 'America/Chicago']));

        $this->assertSame('America/Chicago', TextColumn::make('created_at')->date()->getTimezone());
        $this->assertSame('America/Chicago', TextColumn::make('created_at')->dateTime()->getTimezone());
        $this->assertSame('America/Chicago', TextEntry::make('created_at')->date()->getTimezone());
        $this->assertSame('America/Chicago', TextEntry::make('created_at')->dateTime()->getTimezone());
    }

    public function test_other_columns_and_entries_have_no_timezone(): void
    {
        $this->actingAs(User::factory()->create(['timezone' => 'America/Chicago']));

        $this->assertSame(config('app.timezone'), TextColumn::make('name')->getTimezone());
        $this->assertSame(config('app.timezone'), TextEntry::make('name')->getTimezone());
    }
}
