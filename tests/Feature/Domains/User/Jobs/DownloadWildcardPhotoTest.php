<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Jobs;

use App\Domains\User\Jobs\DownloadWildcardPhotoJob;
use App\Domains\User\Models\User;
use Illuminate\Support\Facades\Storage;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DownloadWildcardPhotoJob::class)]
final class DownloadWildcardPhotoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('platform.wildcard_photo_sync')) {
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

        $directorySearch = $this->createMock(DirectorySearch::class);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($user->username)
            ->willReturn(false);

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($directorySearch);
    }

    public function test_empty_photo_does_nothing(): void
    {
        $user = User::factory()->create();

        Storage::fake('s3');

        $directorySearch = $this->createMock(DirectorySearch::class);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($user->username)
            ->willReturn([]);

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($directorySearch);

        Storage::disk('s3')->assertMissing("wildcard-photos/{$user->username}.jpg");

        $user->refresh();
        $this->assertNull($user->wildcard_photo_s3_key);
        $this->assertNull($user->wildcard_photo_last_synced_at);
    }

    public function test_empty_photo_does_not_erase_existing_s3_key(): void
    {
        $user = User::factory()->create([
            'wildcard_photo_s3_key' => 'wildcard-photos/existing.jpg',
            'wildcard_photo_last_synced_at' => now()->subDay(),
        ]);

        Storage::fake('s3');

        $directorySearch = $this->createMock(DirectorySearch::class);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($user->username)
            ->willReturn([]);

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($directorySearch);

        $user->refresh();
        $this->assertEquals('wildcard-photos/existing.jpg', $user->wildcard_photo_s3_key);
        $this->assertNotNull($user->wildcard_photo_last_synced_at);
    }

    public function test_invalid_base64_does_not_store_or_update(): void
    {
        $user = User::factory()->create();

        Storage::fake('s3');

        $directorySearch = $this->createMock(DirectorySearch::class);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($user->username)
            ->willReturn(['jpegPhoto' => '!!!invalid-base64!!!']);

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($directorySearch);

        Storage::disk('s3')->assertMissing("wildcard-photos/{$user->username}.jpg");

        $user->refresh();
        $this->assertNull($user->wildcard_photo_s3_key);
        $this->assertNull($user->wildcard_photo_last_synced_at);
    }

    public function test_valid_photo_stores_photo_and_updates_user(): void
    {
        $user = User::factory()->create();

        Storage::fake('s3');

        $directorySearch = $this->createMock(DirectorySearch::class);

        $originalPhoto = 'fake-image-data';
        $base64Photo = base64_encode($originalPhoto);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($user->username)
            ->willReturn(['jpegPhoto' => $base64Photo]);

        $expectedPath = "wildcard-photos/{$user->username}.jpg";

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($directorySearch);

        Storage::disk('s3')->assertExists($expectedPath);

        $storedContent = Storage::disk('s3')->get($expectedPath);
        $this->assertEquals($originalPhoto, $storedContent);

        $user->refresh();
        $this->assertEquals($expectedPath, $user->wildcard_photo_s3_key);
        $this->assertNotNull($user->wildcard_photo_last_synced_at);
    }

    public function test_s3_write_failure_does_not_update_user(): void
    {
        $user = User::factory()->create();

        $originalPhoto = 'fake-image-data';
        $base64Photo = base64_encode($originalPhoto);

        $directorySearch = $this->createMock(DirectorySearch::class);

        $directorySearch->expects($this->once())
            ->method('lookupByNetId')
            ->with($user->username)
            ->willReturn(['jpegPhoto' => $base64Photo]);

        Storage::shouldReceive('disk')
            ->with('s3')
            ->andReturnSelf();
        Storage::shouldReceive('put')
            ->once()
            ->andReturn(false);

        $job = new DownloadWildcardPhotoJob($user);
        $job->handle($directorySearch);

        $user->refresh();
        $this->assertNull($user->wildcard_photo_s3_key);
        $this->assertNull($user->wildcard_photo_last_synced_at);
    }
}
