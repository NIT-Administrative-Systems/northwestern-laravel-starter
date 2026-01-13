<?php

declare(strict_types=1);

namespace App\Domains\Core\Contracts;

use App\Console\Commands\ValidateConfigurationCommand;

/**
 * A config validator checks system dependencies and configuration.
 *
 * Validators implementing this interface should be used to verify that various
 * application dependencies (database, filesystem, queue, etc.) are properly
 * configured and functional.
 *
 * Validators should be registered in {@see ValidateConfigurationCommand::validators()}.
 */
interface ConfigValidator
{
    /**
     * A short, human-readable name for this validator.
     *
     * This is displayed as the label during validation output.
     *
     * @example "Environment Variables"
     * @example "Database Connection"
     */
    public function name(): string;

    /**
     * Validate the configuration.
     *
     * Implementations should check if a particular system dependency is
     * properly configured or functional. This method should be safe to
     * run in any environment and should not cause side effects.
     */
    public function validate(): bool;

    /**
     * A brief message describing the successful validation state.
     *
     * This may include dynamic details about the validated resource.
     *
     * @example "Connected to database: my_app"
     * @example "S3 bucket accessible: my-bucket"
     */
    public function successMessage(): string;

    /**
     * A brief message describing the validation failure.
     *
     * Keep this concise; use {@see hints()} for detailed remediation steps.
     */
    public function errorMessage(): string;

    /**
     * Additional hints or remediation steps shown when validation fails.
     *
     * Return an array of actionable suggestions to help resolve the issue.
     * Each hint should be a complete, helpful sentence.
     *
     * @return list<string>
     *
     * @example ['Check that DB_DATABASE is set in your .env file']
     * @example ['Ensure Redis is running: brew services start redis']
     */
    public function hints(): array;
}
