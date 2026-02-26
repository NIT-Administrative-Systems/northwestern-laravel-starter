<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions;

use App\Domains\User\Actions\RecordLogin;
use App\Domains\User\Enums\UserSegmentEnum;
use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RecordLogin::class)]
class RecordLoginTest extends TestCase
{
    public function test_creates_login_record_with_request_metadata(): void
    {
        $user = User::factory()->create();

        $request = Request::create('/test', server: [
            'REMOTE_ADDR' => '192.168.1.50',
            'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        ]);

        $this->action()($user, $request);

        $this->assertCount(1, $user->login_records);

        $record = $user->login_records->first();
        $this->assertNotNull($record->logged_in_at);
        $this->assertEquals(UserSegmentEnum::OTHER, $record->segment);
        $this->assertEquals('192.168.1.50', $record->ip_address);
        $this->assertEquals('TestBrowser/1.0', $record->user_agent);
    }

    public function test_creates_login_record_for_external_user(): void
    {
        $user = User::factory()->affiliate()->create();
        $request = Request::create('/test');

        $this->action()($user, $request);

        $record = UserLoginRecord::sole();
        $this->assertEquals(UserSegmentEnum::EXTERNAL_USER, $record->segment);
    }

    protected function action(): RecordLogin
    {
        return resolve(RecordLogin::class);
    }
}
