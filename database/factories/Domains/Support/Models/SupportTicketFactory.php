<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Support\Models;

use App\Domains\Support\Enums\TicketSystemEnum;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /**
     * @return array<model-property<SupportTicket>, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'details' => fake()->paragraphs(2, true),
            'requester_email' => fake()->companyEmail(),
            'ticketing_system' => TicketSystemEnum::MAIL,
            'ticket_number' => (string) fake()->randomNumber(5, true),
            'post_error' => false,
            'error_message' => null,
            'posted_to_ticketing_system_at' => now(),
        ];
    }

    public function failed(): self
    {
        return $this->state(fn () => [
            'post_error' => true,
            'error_message' => fake()->sentence(),
            'ticket_number' => null,
            'posted_to_ticketing_system_at' => null,
            'fallback_sent_at' => now(),
        ]);
    }

    public function failedCompletely(): self
    {
        return $this->state(fn () => [
            'post_error' => true,
            'error_message' => fake()->sentence(),
            'ticket_number' => null,
            'posted_to_ticketing_system_at' => null,
            'fallback_sent_at' => null,
        ]);
    }

    public function teamDynamix(): self
    {
        return $this->state(fn () => [
            'ticketing_system' => TicketSystemEnum::TEAM_DYNAMIX,
            'ticket_number' => (string) fake()->randomNumber(7, true),
        ]);
    }

    public function pending(): self
    {
        return $this->state(fn () => [
            'ticketing_system' => null,
            'ticket_number' => null,
            'posted_to_ticketing_system_at' => null,
        ]);
    }
}
