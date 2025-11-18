<?php

declare(strict_types=1);

namespace App\Console\Commands\NorthwesternLaravelStarter;

enum ProjectReplacementToken: string
{
    case APPLICATION_NAME = ':app_name';
    case APPLICATION_SLUG = ':app_slug';
    case DATABASE_NAME = ':db_database';
    case TEST_DATABASE_NAME = ':db_test_database';
    case S3_BUCKET_NAME = ':s3_bucket';

    public function question(): string
    {
        return match ($this) {
            self::APPLICATION_NAME => 'What is the official name of your application?',
            self::DATABASE_NAME => 'What will be the name of your local database?',
            self::TEST_DATABASE_NAME => 'What will be the name of your local test database for PHPUnit?',
            self::S3_BUCKET_NAME => 'What will be the name of your local MinIO/S3 bucket?',
            default => '',
        };
    }

    public function placeholder(): string
    {
        return match ($this) {
            self::APPLICATION_NAME => 'e.g. My Application',
            self::DATABASE_NAME => 'e.g. my_application',
            self::TEST_DATABASE_NAME => 'e.g. my_application_test',
            self::S3_BUCKET_NAME => 'e.g. my-application',
            default => '',
        };
    }

    /**
     * For each token, return the file(s) in the project that need to be replaced.
     *
     * @return list<string>
     */
    public function filesToReplace(): array
    {
        return match ($this) {
            self::APPLICATION_NAME, self::DATABASE_NAME, self::TEST_DATABASE_NAME, self::S3_BUCKET_NAME => [
                base_path('.env.example'),
                base_path('.env'),
            ],
            self::APPLICATION_SLUG => [
                base_path('herd.yml'),
            ],
        };
    }
}
