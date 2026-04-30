<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests\Support;

use App\Http\Requests\Support\ContactFormRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ContactFormRequest::class)]
final class ContactFormRequestTest extends TestCase
{
    public function test_authorize_returns_true(): void
    {
        $request = new ContactFormRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_validation_passes_with_valid_data(): void
    {
        $validator = Validator::make([
            'subject' => 'Need help with account access',
            'details' => 'I cannot access my account after an MFA reset.',
        ], new ContactFormRequest()->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_for_missing_fields(): void
    {
        $validator = Validator::make([], new ContactFormRequest()->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('subject', $validator->errors()->toArray());
        $this->assertArrayHasKey('details', $validator->errors()->toArray());
    }

    public function test_validation_fails_for_length_limits(): void
    {
        $validator = Validator::make([
            'subject' => str_repeat('s', 201),
            'details' => str_repeat('d', 10001),
        ], new ContactFormRequest()->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('subject', $validator->errors()->toArray());
        $this->assertArrayHasKey('details', $validator->errors()->toArray());
    }
}
