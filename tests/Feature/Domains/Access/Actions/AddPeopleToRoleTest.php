<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Access\Actions;

use App\Domains\Access\Actions\AddPeopleToRole;
use App\Domains\Access\Enums\RoleModificationOrigin;
use App\Domains\Access\Enums\RoleTypeEnum;
use App\Domains\Access\Enums\SystemPermission;
use App\Domains\Access\Models\Role;
use App\Domains\User\Models\User;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Feature\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectoryTest;
use Tests\TestCase;

#[CoversClass(AddPeopleToRole::class)]
final class AddPeopleToRoleTest extends TestCase
{
    private User $administrator;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        config(['platform.wildcard_photo_sync' => false, 'local-auth.enabled' => true]);

        $this->administrator = User::factory()->affiliate()->create();
        $this->administrator->givePermissionTo(SystemPermission::AssignRoles, SystemPermission::CreateUsers);
        $this->role = Role::factory()->forRoleType(RoleTypeEnum::ApplicationRole)->create(['name' => 'Editors']);

        $directory = [
            'abc123' => ['mail' => 'abc123@northwestern.edu'] + FindOrUpdateUserFromDirectoryTest::studentData('abc123'),
            'def456' => ['mail' => 'def456@northwestern.edu'] + FindOrUpdateUserFromDirectoryTest::studentData('def456'),
        ];
        $directorySearch = $this->createStub(DirectorySearch::class);
        $directorySearch->method('lookup')->willReturnCallback(fn (string $value): array|false => $directory[strtolower($value)] ?? false);
        $this->app->instance(DirectorySearch::class, $directorySearch);
    }

    public function test_a_pasted_list_is_split_on_commas_semicolons_spaces_and_line_breaks_once_each(): void
    {
        $this->assertSame(
            ['abc123', 'guest@example.com', 'def456', 'ghi789'],
            AddPeopleToRole::parse(" abc123, guest@example.com;\n\ndef456\tghi789 ABC123 "),
        );
        $this->assertSame([], AddPeopleToRole::parse(" ,\n "));
    }

    public function test_everyone_in_the_list_is_added_and_everyone_who_isnt_is_listed_with_why(): void
    {
        User::factory()->create(['username' => 'def456'])->assignRoleWithAudit($this->role, RoleModificationOrigin::System);

        $import = resolve(AddPeopleToRole::class)($this->role, "abc123\ndef456\nzzz999\nguest@example.com", $this->administrator);

        $import->refresh();
        $this->assertSame($this->administrator->getKey(), $import->user_id);
        $this->assertSame(4, $import->total_rows);
        $this->assertSame(2, $import->successful_rows);
        $this->assertNotNull($import->completed_at);
        $this->assertTrue(User::query()->where('username', 'abc123')->sole()->hasRole($this->role));
        $this->assertTrue(User::query()->whereEmailEquals('guest@example.com')->sole()->hasRole($this->role));
        $this->assertEqualsCanonicalizing([
            'def456 already has the Editors role.',
            'No one with the NetID or email address zzz999 is in the Northwestern Directory.',
        ], $import->failedRows()->pluck('validation_error')->all());
    }

    public function test_the_person_who_added_them_gets_a_notification_when_the_queue_finishes(): void
    {
        config(['queue.default' => 'database']);

        resolve(AddPeopleToRole::class)($this->role, 'abc123 zzz999', $this->administrator);
        // Run each job as a worker would, without a worker's timeout alarm outliving it.
        while ($job = Queue::connection('database')->pop()) {
            $job->fire();
        }

        $notification = $this->administrator->notifications()->sole();
        $this->assertSame('People Added to Editors', $notification->data['title']);
        $this->assertSame('One person added to the Editors role. One wasn\'t: download the list to see why.', $notification->data['body']);
    }

    // Each chunk is a queued job, and the person who added them hears when it's done.
    public function test_the_list_is_processed_in_queued_chunks(): void
    {
        Bus::fake();
        $people = implode(',', array_map(fn (int $i): string => "guest{$i}@example.com", range(1, AddPeopleToRole::CHUNK_SIZE + 5)));

        resolve(AddPeopleToRole::class)($this->role, $people, $this->administrator);

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2
            && $batch->jobs->every(fn (object $job): bool => $job instanceof ImportCsv));
    }

    public function test_an_empty_or_oversized_list_is_refused(): void
    {
        foreach ([" \n ", implode(' ', range(0, AddPeopleToRole::MAX_PEOPLE))] as $people) {
            try {
                resolve(AddPeopleToRole::class)($this->role, $people, $this->administrator);
                $this->fail('The list was accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Add between 1 and 1000 people at a time.', $exception->getMessage());
            }
        }

        $this->assertSame(0, Import::query()->count());
    }

    public function test_someone_who_may_not_add_people_to_the_role_starts_nothing(): void
    {
        $this->expectException(AuthorizationException::class);

        try {
            resolve(AddPeopleToRole::class)($this->role, 'abc123', User::factory()->create());
        } finally {
            $this->assertSame(0, Import::query()->count());
        }
    }
}
