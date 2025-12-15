<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Domains\User\Enums\AffiliationEnum;
use App\Http\Controllers\Api\V1\UserApiController;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\ApiTestCase;

#[CoversClass(UserApiController::class)]
class UserApiControllerTest extends ApiTestCase
{
    public function endpoint(): string
    {
        return '/api/v1/me';
    }

    public static function methods(): array
    {
        return ['get'];
    }

    public function test_returns_authenticated_user_data(): void
    {
        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'username',
                'auth_type',
                'first_name',
                'last_name',
                'full_name',
                'email',
                'primary_affiliation',
                'departments',
                'job_titles',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    public function test_returns_correct_user_data_for_authenticated_user(): void
    {
        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->apiUser->id);
        $response->assertJsonPath('data.username', $this->apiUser->username);
        $response->assertJsonPath('data.first_name', $this->apiUser->first_name);
        $response->assertJsonPath('data.last_name', $this->apiUser->last_name);
        $response->assertJsonPath('data.email', $this->apiUser->email);
    }

    public function test_returns_full_name_attribute(): void
    {
        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonPath('data.full_name', $this->apiUser->full_name);
        $this->assertEquals(
            $this->apiUser->first_name . ' ' . $this->apiUser->last_name,
            $response->json('data.full_name')
        );
    }

    public function test_returns_auth_type_as_string_value(): void
    {
        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonPath('data.auth_type', $this->apiUser->auth_type->value);
    }

    public function test_returns_primary_affiliation_as_string_value(): void
    {
        $this->apiUser->update(['primary_affiliation' => AffiliationEnum::STAFF]);

        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonPath('data.primary_affiliation', AffiliationEnum::STAFF->value);
    }

    public function test_returns_null_for_nullable_fields(): void
    {
        $this->apiUser->update([
            'primary_affiliation' => null,
            'email' => null,
        ]);

        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonPath('data.primary_affiliation', null);
        $response->assertJsonPath('data.email', null);
    }

    public function test_returns_empty_arrays_for_departments_and_job_titles(): void
    {
        $this->apiUser->update([
            'departments' => [],
            'job_titles' => [],
        ]);

        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonPath('data.departments', []);
        $response->assertJsonPath('data.job_titles', []);
    }

    public function test_returns_populated_departments_and_job_titles(): void
    {
        $departments = ['Computer Science', 'Engineering'];
        $jobTitles = ['Software Engineer', 'Senior Developer'];

        $this->apiUser->update([
            'departments' => $departments,
            'job_titles' => $jobTitles,
        ]);

        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonPath('data.departments', $departments);
        $response->assertJsonPath('data.job_titles', $jobTitles);
        $this->assertCount(2, $response->json('data.departments'));
        $this->assertCount(2, $response->json('data.job_titles'));
    }

    public function test_includes_roles_when_relationship_is_loaded(): void
    {
        $this->apiUser->load('roles');

        $response = $this->authenticatedGet();

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'roles' => [
                    '*' => [
                        'id',
                        'name',
                        'role_type',
                        'guard_name',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ],
        ]);
    }

    public function test_does_not_return_deleted_user(): void
    {
        $this->apiUser->delete();

        $response = $this->authenticatedGet();

        $response->assertUnauthorized();
    }
}
