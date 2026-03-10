<?php

declare(strict_types=1);

namespace App\Domains\Core\Contracts;

use App\Domains\Core\Attributes\StarterValidator;

/**
 * A config validator checks system dependencies and configuration.
 *
 * Validators implementing this interface should be used to verify that various
 * application dependencies (database, filesystem, queue, etc.) are properly
 * configured and functional.
 *
 * Validators are discovered automatically when decorated with the
 * {@see StarterValidator} attribute. The display name is provided via the
 * attribute's `description` parameter rather than a method on this interface.
 */
interface ConfigValidator
{
    /**
     * Whether this validator is relevant to the current configuration.
     *
     * Return false to skip validation entirely for optional integrations
     * that are not configured. Skipped validators are displayed separately
     * from passed/failed results.
     */
    public function shouldRun(): bool;

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
