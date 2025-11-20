<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Health;

use App\Domains\Core\Health\DirectorySearchCheck;
use Illuminate\Support\Facades\Config;
use Mockery;
use Northwestern\SysDev\SOA\DirectorySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;
use Tests\TestCase;

#[CoversClass(DirectorySearchCheck::class)]
class DirectorySearchCheckTest extends TestCase
{
    private DirectorySearchCheck $check;

    private Mockery\MockInterface|DirectorySearch $directorySearchMock;

    private string $testNetId = 'testnetid';

    protected function setUp(): void
    {
        parent::setUp();

        $this->directorySearchMock = Mockery::mock(DirectorySearch::class);
        $this->app->instance(DirectorySearch::class, $this->directorySearchMock);

        Config::set('nusoa.directorySearch.healthCheckNetid', $this->testNetId);

        $this->check = new DirectorySearchCheck();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    public function test_check_passes_on_successful_response(): void
    {
        $expectedResponse = ['uid' => $this->testNetId, 'name' => 'Test User'];
        $this->directorySearchMock->shouldReceive('lookupByNetId')
            ->once()
            ->with($this->testNetId, 'basic')
            ->andReturn($expectedResponse);

        $this->directorySearchMock->shouldReceive('getLastError')
            ->once()
            ->andReturn(null);

        $result = $this->check->run();

        $this->assertInstanceOf(Result::class, $result);
        $this->assertEquals(Status::ok(), $result->status);
        $this->assertEquals('API operational', $result->getShortSummary());
        $this->assertArrayHasKey('tested_netid', $result->meta);
        $this->assertEquals($this->testNetId, $result->meta['tested_netid']);
    }

    public function test_check_fails_on_api_error(): void
    {
        $apiError = 'Network timeout occurred.';
        $this->directorySearchMock->shouldReceive('lookupByNetId')
            ->once()
            ->andReturn(null);

        $this->directorySearchMock->shouldReceive('getLastError')
            ->twice()
            ->andReturn($apiError);

        $result = $this->check->run();

        $this->assertEquals(Status::failed(), $result->status);
        $this->assertEquals('API error', $result->getShortSummary());
        $this->assertEquals("Directory Search API error - {$apiError}", $result->getNotificationMessage());
    }

    public function test_check_fails_on_empty_response(): void
    {
        $this->directorySearchMock->shouldReceive('lookupByNetId')
            ->once()
            ->andReturn(false);

        $this->directorySearchMock->shouldReceive('getLastError')
            ->once()
            ->andReturn(null);

        $result = $this->check->run();

        $this->assertEquals(Status::failed(), $result->status);
        $this->assertEquals('Empty response received', $result->getShortSummary());
        $this->assertEquals("Directory Search API returned no data for test NetID: {$this->testNetId}", $result->getNotificationMessage());
    }

    public function test_check_fails_on_invalid_response_structure_missing_uid(): void
    {
        $invalidResponse = ['display_name' => 'John Doe', 'email' => 'johndoe@nu.edu'];
        $this->directorySearchMock->shouldReceive('lookupByNetId')
            ->once()
            ->andReturn($invalidResponse);

        $this->directorySearchMock->shouldReceive('getLastError')
            ->once()
            ->andReturn(null);

        $result = $this->check->run();

        $this->assertEquals(Status::failed(), $result->status);
        $this->assertEquals('Invalid response structure', $result->getShortSummary());
        $this->assertEquals("Directory Search API response missing required 'uid' field for NetID: {$this->testNetId}", $result->getNotificationMessage());
    }

    public function test_check_warns_when_health_check_netid_is_missing(): void
    {
        Config::set('nusoa.directorySearch.healthCheckNetid', '');

        $this->directorySearchMock->allows('lookupByNetId')->never();

        $result = $this->check->run();

        $this->assertEquals(Status::warning(), $result->status);
        $this->assertEquals('Configuration missing', $result->getShortSummary());
        $this->assertEquals('Health check skipped: Test NetID not configured', $result->getNotificationMessage());
    }
}
