<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Support\Models;

use App\Domains\Support\Models\Changelog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Changelog>
 */
class ChangelogFactory extends Factory
{
    protected $model = Changelog::class;

    /**
     * @return array<model-property<Changelog>, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 year');

        return [
            'slug' => fake()->unique()->date('Y-m-d', $date),
            'title' => fake()->sentence(3),
            'authored_at' => $date,
            'body' => $this->generateBody(),
        ];
    }

    /**
     * Generate a realistic changelog Markdown body with front matter.
     */
    private function generateBody(): string
    {
        $enhancements = [];
        $fixes = [];

        for ($i = 0; $i < fake()->numberBetween(1, 3); $i++) {
            $enhancements[] = sprintf(
                '- **%s** — %s',
                fake()->words(3, true),
                fake()->sentence(),
            );
        }

        for ($i = 0; $i < fake()->numberBetween(0, 2); $i++) {
            $fixes[] = sprintf('- %s', fake()->sentence());
        }

        $body = "### Enhancements\n\n" . implode("\n", $enhancements);

        if ($fixes !== []) {
            $body .= "\n\n### Fixes\n\n" . implode("\n", $fixes);
        }

        return $body;
    }
}
