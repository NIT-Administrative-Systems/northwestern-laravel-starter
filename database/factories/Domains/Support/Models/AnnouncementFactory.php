<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Support\Models;

use App\Domains\Access\Models\Role;
use App\Domains\Support\Enums\AnnouncementAudience;
use App\Domains\Support\Enums\AnnouncementSeverity;
use App\Domains\Support\Models\Announcement;
use App\Domains\User\Enums\Affiliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Live, for everyone signed in, by default.
 *
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<model-property<Announcement>, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'severity' => AnnouncementSeverity::Info,
            'audience' => AnnouncementAudience::Everyone,
            'published_at' => now()->subHour(),
            'starts_at' => now()->subHour(),
            'ends_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null, 'starts_at' => null, 'ends_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now(), 'starts_at' => now()->addDay()]);
    }

    public function ended(): static
    {
        return $this->state(fn () => ['published_at' => now()->subWeek(), 'starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
    }

    public function severity(AnnouncementSeverity $severity): static
    {
        return $this->state(fn () => ['severity' => $severity]);
    }

    public function public(): static
    {
        return $this->state(fn () => ['audience' => AnnouncementAudience::Public]);
    }

    /**
     * @param  list<Role>  $roles
     * @param  list<Affiliation>  $affiliations
     */
    public function targeted(array $roles = [], array $affiliations = []): static
    {
        return $this->state(fn () => [
            'audience' => AnnouncementAudience::Targeted,
            'role_ids' => array_map(fn (Role $role): int => $role->getKey(), $roles),
            'affiliations' => array_map(fn (Affiliation $affiliation): string => $affiliation->value, $affiliations),
        ]);
    }
}
