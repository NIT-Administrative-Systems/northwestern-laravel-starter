<?php

declare(strict_types=1);

namespace App\Domains\User\Jobs;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Northwestern\SysDev\SOA\DirectorySearch;

/**
 * Retrieve a user's Northwestern Wildcard photo from the directory and store it in S3.
 */
class DownloadWildcardPhotoJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $user
    ) {
        //
    }

    public function handle(DirectorySearch $directorySearch): void
    {
        // Non-Northwestern users don't have a Wildcard photo, so there's no action to take.
        if ($this->user->auth_type !== AuthType::SSO || ! config('platform.wildcard_photo_sync')) {
            return;
        }

        $userInfo = retry(3, fn () => $directorySearch->lookupByNetId($this->user->username), 100);

        if ($userInfo === false) {
            return;
        }

        $jpegPhoto = data_get($userInfo, 'jpegPhoto');

        if (blank($jpegPhoto)) {
            return;
        }

        $decodedPhoto = base64_decode((string) $jpegPhoto, strict: true);

        if ($decodedPhoto === false || $decodedPhoto === '') {
            return;
        }

        $photoS3Key = "wildcard-photos/{$this->user->username}.jpg";

        if (! Storage::disk('s3')->put($photoS3Key, $decodedPhoto)) {
            return;
        }

        $this->user->update([
            'wildcard_photo_s3_key' => $photoS3Key,
            'wildcard_photo_last_synced_at' => now(),
        ]);
    }
}
