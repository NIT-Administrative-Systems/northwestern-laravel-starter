<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Core\Contracts\ConfigValidator;
use App\Domains\Core\Services\ConfigValidation\DatabaseValidator;
use App\Domains\Core\Services\ConfigValidation\EnvironmentVariablesValidator;
use App\Domains\Core\Services\ConfigValidation\FilesystemValidator;
use App\Domains\Core\Services\ConfigValidation\QueueValidator;
use Illuminate\Console\Command;

class ValidateConfigurationCommand extends Command
{
    protected $signature = 'config:validate';

    protected $description = 'Validate application configuration';

    /**
     * Configure a list of validators to run against the application configuration.
     *
     * Validators should not include any logic that would cause side effects,
     * and should be safe to run in any environment.
     *
     * @return ConfigValidator[]
     */
    private function validators(): array
    {
        return [
            new EnvironmentVariablesValidator(),
            new DatabaseValidator(),
            new QueueValidator(),
            new FilesystemValidator(),
        ];
    }

    public function handle(): int
    {
        $this->newLine();
        $allPassed = true;

        foreach ($this->validators() as $validator) {
            $passed = $validator->validate();

            $passed
                ? $this->components->success($validator->successMessage())
                : $this->components->error($validator->errorMessage());

            if (! $passed) {
                $allPassed = false;
            }
        }

        $this->newLine();

        return $allPassed
            ? self::SUCCESS
            : self::FAILURE;
    }
}
