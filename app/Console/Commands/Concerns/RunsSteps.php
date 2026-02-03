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
        $exception = null;

        $passed = spin(
            callback: function () use ($callback, &$exception): bool {
                try {
                    $callback();

                    return true;
                } catch (Throwable $e) {
                    $exception = $e;

                    return false;
                }
            },
            message: " {$name}..."
        );

        if ($passed) {
            $this->line("  <fg=green>✓</> {$name}");
        } else {
            $this->line("  <fg=red>✗</> {$name}");
            if ($exception instanceof Throwable) {
                $this->newLine();
                $this->renderException($exception);
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
            $this->components->success($this->successMessage());
        } else {
            $passed = collect($this->results)->where('passed', true)->count();
            $failed = collect($this->results)->where('passed', false)->count();
            $this->components->error("{$passed} passed, {$failed} failed");
        }
    }

    /**
     * Check if all steps passed.
     */
    protected function allPassed(): bool
    {
        return collect($this->results)->every(fn (array $result): bool => $result['passed']);
    }

    /**
     * Render an exception with its full stack trace.
     */
    protected function renderException(Throwable $exception): void
    {
        $class = $exception::class;
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();

        $this->line("  <fg=red;options=bold>{$class}</>");
        $this->line("  <fg=red>{$message}</>");
        $this->newLine();
        $this->line("  <fg=gray>at {$file}:{$line}</>");
        $this->newLine();

        foreach ($exception->getTrace() as $index => $frame) {
            $frameFile = $frame['file'] ?? 'unknown';
            $frameLine = $frame['line'] ?? '?';
            $frameClass = $frame['class'] ?? '';
            $frameType = $frame['type'] ?? '';
            $frameFunction = $frame['function'] ?? '';

            $call = $frameClass ? "{$frameClass}{$frameType}{$frameFunction}()" : "{$frameFunction}()";

            $this->line("  <fg=gray>{$index}. {$frameFile}:{$frameLine}</>");
            $this->line("     <fg=white>{$call}</>");
        }
    }
}
