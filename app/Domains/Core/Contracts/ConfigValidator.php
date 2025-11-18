<?php

declare(strict_types=1);

namespace App\Domains\Core\Contracts;

use App\Console\Commands\ValidateConfigurationCommand;

/**
 * A config validator is a class that checks system dependencies and configuration. Validators should be
 * listed in the {@see ValidateConfigurationCommand::validators()} method.
 *
 * Validators implementing this interface should be used to verify that various
 * application dependencies (database, filesystem, queue, etc.) are properly
 * configured and functional.
 */
interface ConfigValidator
{
    /**
     * Validate the configuration.
     *
     * Implementations should check if a particular system dependency is
     * properly configured or functional. This method should be safe to
     * run in any environment and should not cause side effects.
     */
    public function validate(): bool;

    /**
     * The success message to display when validation passes.
     */
    public function successMessage(): string;

    /**
     * The error message to display when validation fails.
     */
    public function errorMessage(): string;
}
