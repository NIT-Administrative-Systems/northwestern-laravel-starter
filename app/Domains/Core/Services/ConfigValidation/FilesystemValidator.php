<?php

declare(strict_types=1);

namespace App\Domains\Core\Services\ConfigValidation;

use App\Domains\Core\Contracts\ConfigValidator;
use Exception;
use Illuminate\Support\Facades\Storage;

class FilesystemValidator implements ConfigValidator
{
    public function validate(): bool
    {
        try {
            Storage::disk('s3')->files();

            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function successMessage(): string
    {
        $bucket = config('filesystems.disks.s3.bucket');

        return sprintf(
            'S3 connection successful (Bucket: <fg=yellow>%s</>).',
            $bucket,
        );
    }

    public function errorMessage(): string
    {
        if (! $this->hasRequiredS3Config()) {
            return 'S3 configuration is incomplete. Please check your <fg=yellow>.env</> file for ' .
                '<fg=yellow>AWS_ACCESS_KEY_ID</>, <fg=yellow>AWS_SECRET_ACCESS_KEY</>, ' .
                '<fg=yellow>AWS_DEFAULT_REGION</>, and <fg=yellow>AWS_BUCKET</> values.';
        }

        $bucket = config('filesystems.disks.s3.bucket');

        return sprintf(
            'S3 connection failed for bucket <fg=yellow>%s</>. ' .
            'Verify your credentials and ensure the bucket exists and is accessible.',
            $bucket ?? '(undefined)',
        );
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
