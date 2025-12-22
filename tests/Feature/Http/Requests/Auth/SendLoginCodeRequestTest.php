<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests\Auth;

use App\Http\Requests\Auth\SendLoginCodeRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendLoginCodeRequest::class)]
class SendLoginCodeRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new SendLoginCodeRequest();
        $this->assertTrue($request->authorize());
    }

    public function test_validation_passes_with_valid_email(): void
    {
        $data = ['email' => 'user@example.com'];
        $request = new SendLoginCodeRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_with_missing_email(): void
    {
        $data = [];
        $request = new SendLoginCodeRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_validation_fails_with_invalid_email(): void
    {
        $data = ['email' => 'not-an-email'];
        $request = new SendLoginCodeRequest();

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }

    public function test_email_accessor_returns_lowercase(): void
    {
        $request = SendLoginCodeRequest::create('/', 'POST', [
            'email' => 'USER@Example.COM',
        ]);

        $this->assertSame('user@example.com', $request->email());
    }
}
