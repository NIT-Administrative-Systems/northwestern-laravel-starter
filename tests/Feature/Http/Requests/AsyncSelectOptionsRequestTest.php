<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Requests;

use App\Domains\User\Models\User;
use App\Http\Requests\AsyncSelectOptionsRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AsyncSelectOptionsRequest::class)]
class AsyncSelectOptionsRequestTest extends TestCase
{
    public function test_authorize_returns_false_if_not_authenticated(): void
    {
        $request = new AsyncSelectOptionsRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rules_are_valid(): void
    {
        $request = new AsyncSelectOptionsRequest();

        $rules = $request->rules();

        $this->assertArrayHasKey('q', $rules);
        $this->assertArrayHasKey('l', $rules);
    }

    public function test_validation_passes_with_valid_data(): void
    {
        $data = ['q' => 'search term', 'l' => 10];

        $validator = Validator::make($data, new AsyncSelectOptionsRequest()->rules());

        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_with_invalid_data(): void
    {
        $data = ['q' => Str::random(101), 'l' => -1];

        $validator = Validator::make($data, new AsyncSelectOptionsRequest()->rules());

        $this->assertFalse($validator->passes());
        $errors = $validator->errors();

        $this->assertArrayHasKey('q', $errors->toArray());
        $this->assertArrayHasKey('l', $errors->toArray());
    }

    public function test_get_search_query_returns_null_for_array(): void
    {
        $request = AsyncSelectOptionsRequest::create('/', 'GET', ['q' => ['not', 'a', 'string']]);

        $this->assertNull($request->getSearchQuery());
    }

    public function test_get_search_query_returns_null_for_empty(): void
    {
        $request = AsyncSelectOptionsRequest::create('/', 'GET', ['q' => '']);

        $this->assertNull($request->getSearchQuery());
    }

    public function test_get_search_query_returns_string_for_valid_input(): void
    {
        $request = AsyncSelectOptionsRequest::create('/', 'GET', ['q' => 'Search']);

        $this->assertSame('Search', $request->getSearchQuery());
    }

    public function test_get_limit_returns_custom_value(): void
    {
        $request = AsyncSelectOptionsRequest::create('/', 'GET', ['l' => '25']);

        $this->assertSame(25, $request->getLimit());
    }

    public function test_get_limit_returns_default_for_zero(): void
    {
        $request = AsyncSelectOptionsRequest::create('/', 'GET', ['l' => '0']);

        $this->assertSame(100, $request->getLimit());
    }

    public function test_get_limit_returns_default_for_null(): void
    {
        $request = AsyncSelectOptionsRequest::create('/', 'GET');

        $this->assertSame(100, $request->getLimit());
    }

    public function test_failed_validation_returns_json_response(): void
    {
        $this->actingAs(User::factory()->create());

        Route::middleware('web')->post('/async-select', function (AsyncSelectOptionsRequest $request) {
            return response()->json(['ok' => true]);
        });

        $response = $this->postJson('/async-select', ['q' => Str::random(101), 'l' => -1]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'errors' => ['q', 'l'],
        ]);
    }
}
