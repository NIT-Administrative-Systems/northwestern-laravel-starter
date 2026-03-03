<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Core\Contracts\ConfigValidator;
use App\Domains\Core\Services\ConfigValidation\AppKeyValidator;
use App\Domains\Core\Services\ConfigValidation\DatabaseValidator;
use App\Domains\Core\Services\ConfigValidation\DirectorySearchValidator;
use App\Domains\Core\Services\ConfigValidation\EventHubValidator;
use App\Domains\Core\Services\ConfigValidation\FilesystemValidator;
use App\Domains\Core\Services\ConfigValidation\QueueValidator;
use App\Domains\Core\Services\ConfigValidation\SSOValidator;
use Illuminate\Console\Command;
use Throwable;

use function Laravel\Prompts\spin;

/**
 * Validates application configuration and system dependencies.
 *
 * This command runs a series of validators to ensure the application
 * is properly configured and all required services are accessible.
 *
 * @phpstan-type ValidationResult array{validator: ConfigValidator, passed: bool}
 */
class ValidateConfigurationCommand extends Command
{
    protected $signature = 'config:validate';

    protected $description = 'Validate application configuration and system dependencies';

    /** @var list<ValidationResult> */
    protected array $results = [];

    /**
     * Configure a list of validators to run against the application configuration.
     *
     * Validators should not include any logic that would cause side effects,
     * and should be safe to run in any environment.
     *
     * @return list<ConfigValidator>
     */
    protected function validators(): array
    {
        return [
            new AppKeyValidator(),
            new SSOValidator(),
            new DirectorySearchValidator(),
            new DatabaseValidator(),
            new QueueValidator(),
            new FilesystemValidator(),
            new EventHubValidator(),
        ];
    }

    public function handle(): int
    {
        // @phpstan-ignore-next-line
        ray()->disable();

        $this->displayHeader();
        $this->runValidators($this->validators());
        $this->displayResults();
        $this->displaySummary();

        return $this->allPassed() ? self::SUCCESS : self::FAILURE;
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
     * @param  list<ConfigValidator>  $validators
     */
    protected function runValidators(array $validators): void
    {
        foreach ($validators as $validator) {
            $passed = spin(
                callback: function () use ($validator): bool {
                    try {
                        return $validator->validate();
                    } catch (Throwable) {
                        return false;
                    }
                },
                message: "Checking {$validator->name()}..."
            );

            $this->results[] = [
                'validator' => $validator,
                'passed' => $passed,
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
            $validator = $result['validator'];
            $passed = $result['passed'];

            if ($passed) {
                $this->displaySuccess($validator);
            } else {
                $this->displayFailure($validator);
            }
        }
    }

    /**
     * Display a successful validation result.
     */
    protected function displaySuccess(ConfigValidator $validator): void
    {
        $this->line("  <fg=green>✓</> <fg=white>{$validator->name()}</>");
        $this->line("    <fg=gray>{$validator->successMessage()}</>");
        $this->newLine();
    }

    /**
     * Display a failed validation result with hints.
     */
    protected function displayFailure(ConfigValidator $validator): void
    {
        $this->line("  <fg=red>✗</> <fg=white>{$validator->name()}</>");
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
        $passed = collect($this->results)->where('passed', true)->count();
        $failed = collect($this->results)->where('passed', false)->count();
        $total = count($this->results);

        $this->line('  <fg=gray>─────────────────────────────────────────────────</>');
        $this->newLine();

        if ($this->allPassed()) {
            $this->line("  <fg=green>✓</> All {$total} checks passed");
        } else {
            $passedText = "<fg=green>{$passed} passed</>";
            $failedText = "<fg=red>{$failed} failed</>";
            $this->line("  {$passedText}, {$failedText}");
        }

        $this->newLine();
    }

    /**
     * Check if all validators passed.
     */
    protected function allPassed(): bool
    {
        return collect($this->results)->every(fn (array $result): bool => $result['passed']);
    }
}
