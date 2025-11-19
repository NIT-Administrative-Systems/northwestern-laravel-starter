<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Local;

use App\Domains\User\Actions\Local\CreateLocalUser;
use App\Domains\User\Enums\AuthTypeEnum;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CreateLocalUser::class)]
class CreateLocalUserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['auth.local.enabled' => true]);
    }

    public function test_creates_local_user_with_basic_information(): void
    {
        $user = $this->action()(
            email: 'test@example.com',
            firstName: 'John',
            lastName: 'Doe',
            title: 'Developer',
            department: 'Engineering',
            sendLoginLink: false
        );

        $this->assertTrue($user->exists);
        $this->assertEquals(AuthTypeEnum::LOCAL, $user->auth_type);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
        $this->assertEquals(['Developer'], $user->job_titles);
        $this->assertEquals(['Engineering'], $user->departments);
    }

    public function test_normalizes_email_to_lowercase(): void
    {
        $user = $this->action()(
            email: 'Test.User@EXAMPLE.COM',
            firstName: 'Test',
            lastName: 'User',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: false
        );

        $this->assertEquals('test.user@example.com', $user->email);
    }

    public function test_generates_unique_username_from_email(): void
    {
        $user = $this->action()(
            email: 'john.doe@example.com',
            firstName: 'John',
            lastName: 'Doe',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: false
        );

        $this->assertStringStartsWith('johndoe-', $user->username);
        $this->assertMatchesRegularExpression('/^johndoe-[a-zA-Z0-9]{6}$/', $user->username);
    }

    public function test_generates_different_usernames_for_same_email_pattern(): void
    {
        $user1 = $this->action()(
            email: 'test@example.com',
            firstName: 'Test',
            lastName: 'One',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: false
        );

        $user2 = $this->action()(
            email: 'test@different.com',
            firstName: 'Test',
            lastName: 'Two',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: false
        );

        $this->assertNotEquals($user1->username, $user2->username);
        $this->assertStringStartsWith('test-', $user1->username);
        $this->assertStringStartsWith('test-', $user2->username);
    }

    public function test_sends_login_link_by_default(): void
    {
        $user = $this->action()(
            email: 'withlink@example.com',
            firstName: 'Test',
            lastName: 'User',
            title: 'Dev',
            department: 'IT'
            // sendLoginLink defaults to true
        );

        $this->assertEquals(1, $user->login_links()->count());
    }

    public function test_does_not_send_login_link_when_disabled(): void
    {
        $user = $this->action()(
            email: 'nolink@example.com',
            firstName: 'Test',
            lastName: 'User',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: false
        );

        $this->assertEquals(0, $user->login_links()->count());
    }

    public function test_username_collision_generates_new_random_suffix(): void
    {
        $user1 = $this->action()(
            email: 'collision@example.com',
            firstName: 'Test',
            lastName: 'User',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: false
        );

        $user2 = $this->action()(
            email: 'collision@different.com',
            firstName: 'Test',
            lastName: 'User',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: false
        );

        $this->assertNotEquals($user1->username, $user2->username);
    }

    public function test_login_link_uses_request_ip_when_available(): void
    {
        request()->server->set('REMOTE_ADDR', '203.0.113.42');

        $user = $this->action()(
            email: 'iptest@example.com',
            firstName: 'Test',
            lastName: 'User',
            title: 'Dev',
            department: 'IT',
            sendLoginLink: true
        );

        $loginLink = $user->login_links()->first();
        $this->assertEquals('203.0.113.42', $loginLink->requested_ip_address);
    }

    protected function action(): CreateLocalUser
    {
        return resolve(CreateLocalUser::class);
    }
}
