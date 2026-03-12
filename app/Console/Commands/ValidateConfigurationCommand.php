<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Core\Contracts\ConfigValidator;
use App\Domains\Core\Services\ConfigValidatorResolver;
use App\Domains\Core\ValueObjects\ResolvedValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Prompts\spin;

/**
 * Validates application configuration and system dependencies.
 *
 * This command runs a series of validators to ensure the application
 * is properly configured and all required services are accessible.
 *
 * Validators are discovered automatically by scanning for classes
 * implementing {@see ConfigValidator} with the #[StarterValidator] attribute.
 *
 * @phpstan-type ValidationResult array{validator: ConfigValidator, description: string, passed: bool, skipped: bool}
 */
class ValidateConfigurationCommand extends Command
{
    protected $signature = 'config:validate';

    protected $description = 'Validate application configuration and system dependencies';

    /** @var list<ValidationResult> */
    protected array $results = [];

    public function handle(ConfigValidatorResolver $resolver): int
    {
        // @phpstan-ignore-next-line
        ray()->disable();

        $this->displayHeader();
        $this->runValidators($resolver->discover());
        $this->displayResults();
        $this->displaySummary();

        return $this->hasFailed() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Display the command header.
     */
    protected function displayHeader(): void
    {
        $this->newLine();
        $this->components->info('Validating Application Configuration');
    }

    /**
     * Run all validators and collect results.
     *
     * @param  list<ResolvedValidator>  $resolvedValidators
     */
    protected function runValidators(array $resolvedValidators): void
    {
        foreach ($resolvedValidators as $resolved) {
            if (! $resolved->validator->shouldRun()) {
                $this->results[] = [
                    'validator' => $resolved->validator,
                    'description' => $resolved->description,
                    'passed' => false,
                    'skipped' => true,
                ];

                continue;
            }

            $validatorException = null;

            $passed = spin(
                callback: function () use ($resolved, &$validatorException): bool {
                    try {
                        return $resolved->validator->validate();
                    } catch (Throwable $e) {
                        $validatorException = $e;

                        return false;
                    }
                },
                message: "Checking {$resolved->description}..."
            );

            if ($validatorException instanceof Throwable) {
                Log::warning('Config validator threw an exception', [
                    'validator' => $resolved->validator::class,
                    'description' => $resolved->description,
                    'exception' => $validatorException->getMessage(),
                    'exception_class' => get_class($validatorException),
                ]);
            }

            $this->results[] = [
                'validator' => $resolved->validator,
                'description' => $resolved->description,
                'passed' => $passed,
                'skipped' => false,
            ];
        }
    }

    /**
     * Display the validation results.
     */
    protected function displayResults(): void
    {
        $this->newLine();

        foreach ($this->results as $result) {
            if ($result['skipped']) {
                $this->displaySkipped($result['description']);
            } elseif ($result['passed']) {
                $this->displaySuccess($result['description'], $result['validator']);
            } else {
                $this->displayFailure($result['description'], $result['validator']);
            }
        }
    }

    /**
     * Display a skipped validation result.
     */
    protected function displaySkipped(string $description): void
    {
        $this->line("  <fg=yellow>–</> <fg=white>{$description}</>");
        $this->line('    <fg=gray>Skipped (not applicable)</>');
        $this->newLine();
    }

    /**
     * Display a successful validation result.
     */
    protected function displaySuccess(string $description, ConfigValidator $validator): void
    {
        $this->line("  <fg=green>✓</> <fg=white>{$description}</>");
        $this->line("    <fg=gray>{$validator->successMessage()}</>");
        $this->newLine();
    }

    /**
     * Display a failed validation result with hints.
     */
    protected function displayFailure(string $description, ConfigValidator $validator): void
    {
        $this->line("  <fg=red>✗</> <fg=white>{$description}</>");
        $this->line("    <fg=red>{$validator->errorMessage()}</>");

        $hints = $validator->hints();
        if (count($hints) > 0) {
            $this->newLine();
            foreach ($hints as $hint) {
                $this->line("    <fg=gray>→</> {$hint}");
            }
        }

        $this->newLine();
    }

    /**
     * Display the summary of all validation results.
     */
    protected function displaySummary(): void
    {
        $passed = collect($this->results)->where('passed', true)->where('skipped', false)->count();
        $failed = collect($this->results)->where('passed', false)->where('skipped', false)->count();
        $skipped = collect($this->results)->where('skipped', true)->count();

        $this->line('  <fg=gray>─────────────────────────────────────────────────</>');
        $this->newLine();

        if (! $this->hasFailed()) {
            $parts = ["{$passed} passed"];
            if ($skipped > 0) {
                $parts[] = "{$skipped} skipped";
            }
            $this->line('  <fg=green>✓</> ' . implode(', ', $parts));
        } else {
            $passedText = "<fg=green>{$passed} passed</>";
            $failedText = "<fg=red>{$failed} failed</>";
            $skippedText = $skipped > 0 ? ", <fg=yellow>{$skipped} skipped</>" : '';
            $this->line("  {$passedText}, {$failedText}{$skippedText}");
        }

        $this->newLine();
    }

    /**
     * Check if any validators failed.
     */
    protected function hasFailed(): bool
    {
        return collect($this->results)
            ->where('skipped', false)
            ->contains(fn (array $result): bool => ! $result['passed']);
    }
}
