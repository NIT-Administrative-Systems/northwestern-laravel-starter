<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Directory;

use App\Domains\User\Actions\Directory\FindOrUpdateUserFromDirectory;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Exceptions;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FindOrUpdateUserFromDirectory::class)]
class FindOrUpdateUserFromDirectoryTest extends TestCase
{
    public function test_invoke(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);

        User::factory()->create(['username' => 'xyz456']);
        $inactiveUser = $this->service(['directorySearch' => $directoryApi])('xyz456');

        $this->assertTrue($inactiveUser->exists);
        $this->assertTrue($inactiveUser->netid_inactive);
        $this->assertNotNull($inactiveUser->directory_sync_last_failed_at);
        $this->assertEquals('xyz456', $inactiveUser->username);

        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));
        $activeUser = $this->service(['directorySearch' => $directoryApi])('abc123');

        $this->assertTrue($activeUser->exists);
        $this->assertFalse($activeUser->netid_inactive);
        $this->assertNull($activeUser->directory_sync_last_failed_at);
        $this->assertEquals('abc123', $activeUser->username);
        $this->assertEquals(AffiliationEnum::STUDENT, $activeUser->primary_affiliation);
    }

    public function test_missing_affiliation_throws_exception(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);

        $directoryApi->method('lookup')->willReturn([
            'uid' => 'missing',
            'mail' => 'no-affiliation@northwestern.edu',
            'givenName' => ['No'],
            'sn' => ['Affiliation'],
        ]);

        $this->expectException(BadDirectoryEntry::class);
        $this->expectExceptionMessage('missing');

        $this->service(['directorySearch' => $directoryApi])('missing');
    }

    public function test_missing_affiliation_returns_existing_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);

        $existingUser = User::factory()
            ->create([
                'username' => 'existing',
                'last_directory_sync_at' => null,
            ]);

        $directoryApi->method('lookup')->willReturn([
            'uid' => 'existing',
            'mail' => $existingUser->email,
            'givenName' => ['Existing'],
            'sn' => ['User'],
        ]);

        $user = $this->service(['directorySearch' => $directoryApi])('existing');

        $this->assertTrue($user->exists);
        $this->assertEquals('existing', $user->username);
        $this->assertTrue($user->netid_inactive);
        $this->assertNotNull($user->directory_sync_last_failed_at);
        // A sync wasn't actually performed since the directory entry is bad
        $this->assertNull($user->last_directory_sync_at);
    }

    public function test_missing_email_throws_exception_when_no_existing_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn([
            'uid' => 'no-email',
            'eduPersonPrimaryAffiliation' => 'student',
            'givenName' => ['No'],
            'sn' => ['Email'],
        ]);

        $this->expectException(BadDirectoryEntry::class);
        $this->expectExceptionMessage('no-email');
        $this->service(['directorySearch' => $directoryApi])('no-email');
    }

    public function test_returns_null_if_existing_user_is_api_type(): void
    {
        User::factory()->create([
            'username' => 'api-user',
            'auth_type' => AuthTypeEnum::API,
        ]);

        $result = $this->service()('api-user');

        $this->assertNull($result);
    }

    public function test_catches_exception_during_immediate_job_dispatch(): void
    {
        config(['platform.wildcard_photo_sync' => true]);

        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldReceive('dispatchSync')
                ->once()
                ->andThrow(new \Exception('Job dispatch failed'));
        });

        Exceptions::fake();

        $user = $this->service(['directorySearch' => $directoryApi])('abc123', immediate: true);

        $this->assertInstanceOf(User::class, $user);

        Exceptions::assertReported(function (\Exception $e) {
            return $e->getMessage() === 'Job dispatch failed';
        });
    }

    public function test_missing_email_returns_existing_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $existingUser = User::factory()->create([
            'username' => 'existing-no-email',
            'auth_type' => AuthTypeEnum::SSO,
            'email' => 'existing@northwestern.edu',
            'netid_inactive' => false,
        ]);

        $directoryData = [
            'uid' => 'existing-no-email',
            'eduPersonPrimaryAffiliation' => 'student',
            'givenName' => ['Existing'],
            'sn' => ['User'],
        ];

        $directoryApi->method('lookup')->willReturn($directoryData);

        $user = $this->service(['directorySearch' => $directoryApi])('existing-no-email');

        $this->assertTrue($user->exists);
        $this->assertEquals('existing-no-email', $user->username);
        $this->assertEquals($existingUser->id, $user->id);
        $this->assertTrue($user->netid_inactive);
        $this->assertNotNull($user->directory_sync_last_failed_at);
        $this->assertEquals('existing@northwestern.edu', $user->email);
    }

    public function test_missing_mail_field_but_has_student_email(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        User::factory()->create([
            'username' => 'missing-mail',
            'auth_type' => AuthTypeEnum::SSO,
            'netid_inactive' => false,
        ]);

        $directoryApi->method('lookup')->willReturn([
            'uid' => 'missing-mail',
            'eduPersonPrimaryAffiliation' => 'student',
            'nuStudentEmail' => 'student@u.northwestern.edu',
            'givenName' => ['Missing'],
            'sn' => ['Mail'],
        ]);

        $user = $this->service(['directorySearch' => $directoryApi])('missing-mail');

        $this->assertTrue($user->exists);
        $this->assertEquals('missing-mail', $user->username);
        $this->assertTrue($user->netid_inactive);
        $this->assertNotNull($user->directory_sync_last_failed_at);
    }

    public function test_no_directory_data_throws_exception_when_no_existing_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn([]);

        $this->expectException(BadDirectoryEntry::class);
        $this->expectExceptionMessage('nonexistent');
        $this->service(['directorySearch' => $directoryApi])('nonexistent');
    }

    public function test_no_directory_data_returns_existing_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $existingUser = User::factory()->create([
            'username' => 'existing-no-data',
            'auth_type' => AuthTypeEnum::SSO,
            'netid_inactive' => false,
        ]);

        $directoryApi->method('lookup')->willReturn([]);

        $user = $this->service(['directorySearch' => $directoryApi])('existing-no-data');

        $this->assertTrue($user->exists);
        $this->assertEquals('existing-no-data', $user->username);
        $this->assertEquals($existingUser->id, $user->id);
        $this->assertTrue($user->netid_inactive);
        $this->assertNotNull($user->directory_sync_last_failed_at);
    }

    public function test_does_not_mark_non_sso_user_as_inactive(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $existingUser = User::factory()->create([
            'username' => 'local-user',
            'auth_type' => AuthTypeEnum::LOCAL,
            'netid_inactive' => false,
        ]);

        $directoryApi->method('lookup')->willReturn([]);

        $user = $this->service(['directorySearch' => $directoryApi])('local-user');

        $this->assertTrue($user->exists);
        $this->assertEquals('local-user', $user->username);
        $this->assertEquals($existingUser->id, $user->id);
        $this->assertFalse($user->netid_inactive);
        $this->assertNull($user->directory_sync_last_failed_at);
    }

    public function test_resets_inactive_flag_after_successful_sync(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')
            ->willReturnOnConsecutiveCalls(
                false,
                self::studentData('recovered')
            );

        $user = User::factory()->create(['username' => 'recovered']);

        $this->assertNull($user->netid_inactive);
        $this->assertNull($user->directory_sync_last_failed_at);

        $user = $this->service(['directorySearch' => $directoryApi])('recovered');

        $this->assertTrue($user->netid_inactive);
        $this->assertNotNull($user->directory_sync_last_failed_at);

        $user = $this->service(['directorySearch' => $directoryApi])('recovered');

        $this->assertFalse($user->netid_inactive);
        $this->assertNotNull($user->directory_sync_last_failed_at);
    }

    public function test_get_last_error(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('getLastError')->willReturn('Foobar error');

        $this->assertEquals('Foobar error', $this->service(['directorySearch' => $directoryApi])->getLastError());
    }

    public function test_empty_search_value_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search value cannot be empty');

        $this->service()('');
    }

    public function test_whitespace_only_search_value_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search value cannot be empty');

        $this->service()('   ');
    }

    public function test_email_search_creates_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        $user = $this->service(['directorySearch' => $directoryApi])('student@northwestern.edu');

        $this->assertTrue($user->exists);
        $this->assertEquals('abc123', $user->username);
        $this->assertEquals('StudieNU20XX@u.northwestern.edu', $user->email); // Student email has priority
        $this->assertEquals(AffiliationEnum::STUDENT, $user->primary_affiliation);
    }

    public function test_employee_id_search_creates_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        $user = $this->service(['directorySearch' => $directoryApi])('1234567');

        $this->assertTrue($user->exists);
        $this->assertEquals('abc123', $user->username);
        $this->assertEquals('StudieNU20XX@u.northwestern.edu', $user->email); // Student email has priority
        $this->assertEquals(AffiliationEnum::STUDENT, $user->primary_affiliation);
    }

    public function test_netid_search_with_mixed_case(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        $user = $this->service(['directorySearch' => $directoryApi])('ABC123');

        $this->assertTrue($user->exists);
        $this->assertEquals('abc123', $user->username);
    }

    public function test_email_search_with_mixed_case(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        $user = $this->service(['directorySearch' => $directoryApi])('Student@Northwestern.Edu');

        $this->assertTrue($user->exists);
        $this->assertEquals('abc123', $user->username);
    }

    public function test_search_with_whitespace_gets_trimmed(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        $user = $this->service(['directorySearch' => $directoryApi])('  abc123  ');

        $this->assertTrue($user->exists);
        $this->assertEquals('abc123', $user->username);
    }

    public function test_immediate_job_execution_continues_on_job_failure(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        config(['platform.wildcard_photo_sync' => true]);

        $user = $this->service(['directorySearch' => $directoryApi])('abc123', immediate: true);

        $this->assertTrue($user->exists);
        $this->assertEquals('abc123', $user->username);
        // User should still be created even if jobs fail
    }

    public function test_finds_existing_user_by_email(): void
    {
        $existingUser = User::factory()->create([
            'email' => 'existing@northwestern.edu',
            'auth_type' => AuthTypeEnum::SSO,
        ]);

        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(array_merge(
            self::studentData('abc123'),
            ['mail' => 'existing@northwestern.edu']
        ));

        $user = $this->service(['directorySearch' => $directoryApi])('existing@northwestern.edu');

        $this->assertEquals($existingUser->id, $user->id);
        $this->assertTrue($user->exists);
    }

    public function test_finds_existing_user_by_employee_id(): void
    {
        $existingUser = User::factory()->create([
            'employee_id' => '1000000',  // Match the studentData
            'username' => 'abc123',      // Match the NetID in directory data
            'auth_type' => AuthTypeEnum::SSO,
        ]);

        $directoryApi = $this->createStub(DirectorySearch::class);
        $directoryApi->method('lookup')->willReturn(self::studentData('abc123'));

        $user = $this->service(['directorySearch' => $directoryApi])('1000000');

        $this->assertEquals($existingUser->id, $user->id);
        $this->assertTrue($user->exists);
    }

    public function test_blank_email_returns_existing_user(): void
    {
        $directoryApi = $this->createStub(DirectorySearch::class);

        $existingUser = User::factory()->create([
            'username' => '1106545',
            'employee_id' => '1106545',
            'email' => 'student@northwestern.edu',
            'netid_inactive' => false,
        ]);

        $directoryApi->method('lookup')->willReturn([
            'uid' => '1106545',
            'mail' => '',
            'nuStudentEmail' => '',
            'givenName' => ['Student'],
            'sn' => ['Name'],
            'eduPersonPrimaryAffiliation' => 'student',
        ]);

        $user = $this->service(['directorySearch' => $directoryApi])('1106545');

        $this->assertTrue($user->exists);
        $this->assertEquals('1106545', $user->username);
        $this->assertEquals($existingUser->id, $user->id);
        $this->assertEquals('student@northwestern.edu', $user->email);
        $this->assertTrue($user->netid_inactive);
        $this->assertNotNull($user->directory_sync_last_failed_at);
    }

    /**
     * @param  array<string, mixed>  $dependencies
     */
    protected function service(array $dependencies = []): FindOrUpdateUserFromDirectory
    {
        return resolve(FindOrUpdateUserFromDirectory::class, $dependencies);
    }

    /**
     * @return array<string, mixed>
     */
    public static function studentData(string $netid): array
    {
        return [
            'nuAllSchoolAffiliations' => ['student'],
            'eduPersonPrimaryAffiliation' => 'student',
            'givenName' => [
                'Studie',
            ],
            'sn' => [
                'NU',
            ],
            'uid' => $netid,
            'mail' => 'student-worker@northwestern.edu',
            'nuStudentEmail' => 'StudieNU20XX@u.northwestern.edu',
            'telephoneNumber' => '',
            'address' => '',
            'eduPersonAffiliation' => [
                'employee',
                'member',
                'student',
            ],
            'nuLegalSn' => '',
            'nuLegalGivenName' => '',
            'employeeNumber' => '1000000',
            'nuStudentNumber' => '1000000',
            'nuAllStudentCurrentPhone' => [
                '+1 573 111 1111',
            ],
            'nuAllStudentCurrentAddress' => [
                '1801 Maple Ave$Apt 401$Chicago, IL 60640$USA',
            ],
            'nuStudentGivenName' => 'Studie',
            'nuStudentSn' => 'NU',
            'nuStudentLegalGivenName' => 'Student',
            'nuStudentLegalSn' => 'NU',
        ];
    }
}
