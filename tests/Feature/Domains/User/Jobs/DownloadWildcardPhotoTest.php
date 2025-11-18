<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Jobs;

use App\Domains\User\Jobs\DownloadWildcardPhotoJob;
use App\Domains\User\Models\User;
use App\Domains\User\Repositories\UserRepository;
use Illuminate\Support\Facades\Storage;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DownloadWildcardPhotoJob::class)]
class DownloadWildcardPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('platform.wildcard_photo_sync.enabled')) {
            $this->markTestSkipped('Wildcard photo sync is not enabled');
        }
    }

    public function test_non_sso_user_does_nothing(): void
    {
        $user = User::factory()->affiliate()->create();

        DownloadWildcardPhotoJob::dispatchSync($user);

        $user->refresh();

        $this->assertNull($user->wildcard_photo_s3_key);
        $this->assertNull($user->wildcard_photo_last_synced_at);
    }

    public function test_directory_search_returns_false_does_nothing(): void
    {
        $user = User::factory()->create();

        $userRepository = $this->createMock(UserRepository::class);
        $directorySearch = $this->createMock(DirectorySearch::class);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($this->equalTo($user->username))
            ->willReturn(false);

        $userRepository->expects($this->never())->method('updateWildcardPhoto');

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($userRepository, $directorySearch);
    }

    public function test_empty_photo_updates_null_without_storage_call(): void
    {
        $user = User::factory()->create();

        Storage::fake('s3');

        $userRepository = $this->createMock(UserRepository::class);
        $directorySearch = $this->createMock(DirectorySearch::class);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($this->equalTo($user->username))
            ->willReturn([]);

        $userRepository->expects($this->once())
            ->method('updateWildcardPhoto')
            ->with($this->equalTo($user), $this->equalTo(null));

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($userRepository, $directorySearch);

        Storage::disk('s3')->assertMissing("wildcard-photos/{$user->username}.jpg");
    }

    public function test_valid_photo_stores_photo_and_updates_user(): void
    {
        $user = User::factory()->create();

        Storage::fake('s3');

        $userRepository = $this->createMock(UserRepository::class);
        $directorySearch = $this->createMock(DirectorySearch::class);

        $originalPhoto = 'fake-image-data';
        $base64Photo = base64_encode($originalPhoto);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($this->equalTo($user->username))
            ->willReturn(['jpegPhoto' => $base64Photo]);

        $expectedPath = "wildcard-photos/{$user->username}.jpg";

        $userRepository->expects($this->once())
            ->method('updateWildcardPhoto')
            ->with($this->equalTo($user), $this->equalTo($expectedPath));

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($userRepository, $directorySearch);

        Storage::disk('s3')->assertExists($expectedPath);

        $storedContent = Storage::disk('s3')->get($expectedPath);
        $this->assertEquals($originalPhoto, $storedContent);
    }
}
