<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Attributes\StarterValidator;
use App\Domains\Core\Contracts\ConfigValidator;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Validates the S3 filesystem connection is configured and accessible.
 */
#[StarterValidator(description: 'S3 Storage')]
class FilesystemValidator implements ConfigValidator
{
    protected ?string $errorReason = null;

    public function shouldRun(): bool
    {
        return true;
    }

    public function validate(): bool
    {
        if (! $this->hasRequiredS3Config()) {
            $this->errorReason = 'not_configured';

            return false;
        }

        try {
            Storage::disk('s3')->files();

            return true;
        } catch (Throwable) {
            $this->errorReason = 'connection_failed';

            return false;
        }
    }

    public function successMessage(): string
    {
        $bucket = config('filesystems.disks.s3.bucket');

        return "Connected to S3 bucket <comment>{$bucket}</comment>";
    }

    public function errorMessage(): string
    {
        return match ($this->errorReason) {
            'not_configured' => 'S3 configuration is incomplete',
            default => 'Unable to access S3 bucket',
        };
    }

    public function hints(): array
    {
        $bucket = config('filesystems.disks.s3.bucket');

        return match ($this->errorReason) {
            'not_configured' => [
                'Set <comment>AWS_ACCESS_KEY_ID</comment> in your .env file',
                'Set <comment>AWS_SECRET_ACCESS_KEY</comment> in your .env file',
                'Set <comment>AWS_DEFAULT_REGION</comment> in your .env file',
                'Set <comment>AWS_BUCKET</comment> in your .env file',
            ],
            default => [
                "Verify the bucket <comment>{$bucket}</comment> exists and is accessible",
                'Check that your AWS credentials have the correct permissions',
                'Ensure <comment>AWS_DEFAULT_REGION</comment> matches your bucket location',
            ],
        };
    }

    /**
     * Check if all required S3 configuration parameters are set.
     */
    private function hasRequiredS3Config(): bool
    {
        $requiredConfig = [
            'filesystems.disks.s3.key',
            'filesystems.disks.s3.secret',
            'filesystems.disks.s3.region',
            'filesystems.disks.s3.bucket',
        ];

        return array_all($requiredConfig, fn ($config) => filled(config($config)));
    }
}
