<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\spin;

/**
 * Provides step-by-step execution with spinners, progress tracking, and summary display.
 *
 * Commands using this trait should override the `successMessage()` method to customize
 * the success summary message (e.g., "Database rebuild complete").
 *
 * @mixin Command
 */
trait RunsSteps
{
    /** @var list<array{passed: bool}> */
    protected array $results = [];

    /**
     * Get the message to display when all steps pass.
     *
     * Override this method in the using class to customize the success message.
     */
    protected function successMessage(): string
    {
        return 'All steps completed successfully';
    }

    /**
     * Run a step with a spinner, display the result immediately, and track it.
     *
     * @param  callable(): mixed  $callback
     */
    protected function runStep(string $name, callable $callback): bool
    {
        $error = null;

        $passed = spin(
            callback: function () use ($callback, &$error): bool {
                try {
                    $callback();

                    return true;
                } catch (Throwable $e) {
                    $error = $e->getMessage();

                    return false;
                }
            },
            message: "{$name}..."
        );

        if ($passed) {
            $this->line("  <fg=green>✓</> {$name}");
        } else {
            $this->line("  <fg=red>✗</> {$name}");
            if ($error) {
                $this->line("    <fg=red>{$error}</>");
            }
        }

        $this->results[] = ['passed' => $passed];

        return $passed;
    }

    /**
     * Display a summary of all step results.
     */
    protected function displaySummary(): void
    {
        $this->newLine();
        $this->line('  <fg=gray>─────────────────────────────────────────────────</>');
        $this->newLine();

        if ($this->allPassed()) {
            $this->line("  <fg=green>✓</> {$this->successMessage()}");
        } else {
            $passed = collect($this->results)->where('passed', true)->count();
            $failed = collect($this->results)->where('passed', false)->count();
            $this->line("  <fg=green>{$passed} passed</>, <fg=red>{$failed} failed</>");
        }

        $this->newLine();
    }

    /**
     * Check if all steps passed.
     */
    protected function allPassed(): bool
    {
        return collect($this->results)->every(fn (array $result): bool => $result['passed']);
    }
}
