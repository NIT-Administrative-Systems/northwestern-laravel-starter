<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\User\Actions\StartImpersonation;
use App\Domains\User\Models\User;
use Lab404\Impersonate\Services\ImpersonateManager;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(StartImpersonation::class)]
class StartImpersonationTest extends TestCase
{
    public function test_user_cannot_impersonate_throws_403(): void
    {
        $managerMock = Mockery::mock(ImpersonateManager::class);
        $userMock = Mockery::mock(User::class);

        $userMock->expects('canImpersonate')
            ->andReturns(false);

        $managerMock->allows('isImpersonating')->never();

        $action = new StartImpersonation($managerMock);

        try {
            $action($userMock, 999, 'web');
            $this->fail('Expected exception to be thrown');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(HttpException::class, $e);
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_self_impersonation_throws_403(): void
    {
        $managerMock = Mockery::mock(ImpersonateManager::class);
        $userMock = Mockery::mock(User::class);

        $userMock->expects('canImpersonate')
            ->andReturns(true);

        $managerMock->allows('getDefaultSessionGuard')
            ->andReturns('web');

        $managerMock->expects('getCurrentAuthGuardName')
            ->andReturns('web');

        // User ID matches the impersonation target ID
        $userMock->expects('getAuthIdentifier')
            ->andReturns(999);

        $managerMock->allows('isImpersonating')->never();

        $action = new StartImpersonation($managerMock);

        try {
            $action($userMock, 999, 'web');
            $this->fail('Expected exception to be thrown');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(HttpException::class, $e);
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_impersonation_already_active_throws_403(): void
    {
        $managerMock = Mockery::mock(ImpersonateManager::class);
        $userMock = Mockery::mock(User::class);

        $userMock->expects('canImpersonate')
            ->andReturns(true);

        $managerMock->allows('getDefaultSessionGuard')
            ->andReturns('web');

        $managerMock->expects('getCurrentAuthGuardName')
            ->andReturns('web');

        $userMock->expects('getAuthIdentifier')
            ->andReturns(1);

        // Already impersonating
        $managerMock->expects('isImpersonating')
            ->andReturns(true);

        $action = new StartImpersonation($managerMock);

        try {
            $action($userMock, 2, 'web');
            $this->fail('Expected exception to be thrown');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(HttpException::class, $e);
            $this->assertEquals(403, $e->getStatusCode());
        }
    }

    public function test_impersonation_succeeds_and_returns_redirect_url(): void
    {
        $managerMock = Mockery::mock(ImpersonateManager::class);
        $userMock = Mockery::mock(User::class);
        $impersonatedMock = Mockery::mock(User::class);

        $userMock->expects('canImpersonate')
            ->andReturns(true);

        $managerMock->allows('getDefaultSessionGuard')
            ->andReturns('web');

        $managerMock->expects('getCurrentAuthGuardName')
            ->andReturns('web');

        $userMock->expects('getAuthIdentifier')
            ->andReturns(1);

        $managerMock->expects('isImpersonating')
            ->andReturns(false);

        $managerMock->expects('findUserById')
            ->with(2, 'web')
            ->andReturns($impersonatedMock);

        $impersonatedMock->expects('canBeImpersonated')
            ->andReturns(true);

        $managerMock->expects('take')
            ->with($userMock, $impersonatedMock, 'web')
            ->andReturns(true);

        $managerMock->expects('getTakeRedirectTo')
            ->andReturns('https://example.com/dashboard');

        $action = new StartImpersonation($managerMock);
        $redirectUrl = $action($userMock, 2, 'web');

        $this->assertEquals('https://example.com/dashboard', $redirectUrl);
    }

    public function test_user_that_cannot_be_impersonated_redirects_back(): void
    {
        $managerMock = Mockery::mock(ImpersonateManager::class);
        $userMock = Mockery::mock(User::class);
        $impersonatedMock = Mockery::mock(User::class);

        $userMock->expects('canImpersonate')
            ->andReturns(true);

        $managerMock->allows('getDefaultSessionGuard')
            ->andReturns('web');

        $managerMock->expects('getCurrentAuthGuardName')
            ->andReturns('web');

        $userMock->expects('getAuthIdentifier')
            ->andReturns(1);

        $managerMock->expects('isImpersonating')
            ->andReturns(false);

        $managerMock->expects('findUserById')
            ->with(2, 'web')
            ->andReturns($impersonatedMock);

        $impersonatedMock->expects('canBeImpersonated')
            ->andReturns(false);

        $managerMock->expects('take')
            ->never();

        $managerMock->expects('getTakeRedirectTo')
            ->never();

        $action = new StartImpersonation($managerMock);
        $redirectUrl = $action($userMock, 2, 'web');

        $this->assertEquals('back', $redirectUrl);
    }
}
