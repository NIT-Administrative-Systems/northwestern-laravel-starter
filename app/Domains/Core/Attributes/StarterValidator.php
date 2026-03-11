<?php

declare(strict_types=1);

namespace App\Domains\Core\Attributes;

use App\Domains\Core\Contracts\ConfigValidator;
use Attribute;

/**
 * Marks a config validator for automatic discovery by the config:validate command.
 *
 * Validators decorated with this attribute are automatically discovered and
 * executed during configuration validation. This follows the same
 * convention as {@see AutoSeed} for seeder discovery.
 *
 * Requirements:
 * - Must implement {@see ConfigValidator}
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class StarterValidator
{
    /**
     * @param  non-empty-string  $description  Human-readable label displayed during validation output
     */
    public function __construct(
        public string $description,
    ) {
        //
    }
}
