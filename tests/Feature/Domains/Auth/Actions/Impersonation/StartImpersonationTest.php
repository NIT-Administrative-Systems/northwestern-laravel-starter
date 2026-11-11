<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Actions\Impersonation;

use App\Domains\Auth\Actions\Impersonation\StartImpersonation;
use App\Domains\User\Models\User;
use Lab404\Impersonate\Services\ImpersonateManager;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[CoversClass(StartImpersonation::class)]
final class StartImpersonationTest extends TestCase
{
    public function test_someone_without_the_permission_is_refused(): void
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
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_nobody_impersonates_themselves(): void
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
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_impersonating_while_already_impersonating_is_refused(): void
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
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_impersonating_someone_who_does_not_exist_is_not_found(): void
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

        $managerMock->expects('isImpersonating')
            ->andReturns(false);

        $managerMock->expects('findUserById')
            ->with(999, 'web')
            ->andReturns(null);

        $action = new StartImpersonation($managerMock);

        try {
            $action($userMock, 999, 'web');
            $this->fail('Expected exception to be thrown');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(HttpException::class, $e);
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_impersonating_someone_returns_where_to_go_next(): void
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

        $this->assertSame('https://example.com/dashboard', $redirectUrl);
    }

    public function test_someone_who_cannot_be_impersonated_sends_the_administrator_back(): void
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

        $this->assertSame('back', $redirectUrl);
    }
}
