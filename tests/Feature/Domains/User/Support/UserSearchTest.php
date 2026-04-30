<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Support;

use App\Domains\User\Models\User;
use App\Domains\User\Models\UserLoginRecord;
use App\Domains\User\QueryBuilders\UserBuilder;
use App\Domains\User\Support\UserOptionLabel;
use App\Domains\User\Support\UserSearch;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserSearch::class)]
#[CoversClass(UserOptionLabel::class)]
final class UserSearchTest extends TestCase
{
    public function test_searches_by_username_first_name_last_name_and_email(): void
    {
        $user = User::factory()->create([
            'username' => 'phase-one-user',
            'first_name' => 'Marisol',
            'last_name' => 'Quintero',
            'email' => 'marisol.quintero@example.test',
        ]);

        $service = resolve(UserSearch::class);

        $this->assertSame(
            [$user->id => 'Marisol Quintero (phase-one-user)'],
            $service->options('phase-one-user'),
        );

        $this->assertSame(
            [$user->id => 'Marisol Quintero (phase-one-user)'],
            $service->options('Marisol'),
        );

        $this->assertSame(
            [$user->id => 'Marisol Quintero (phase-one-user)'],
            $service->options('Quintero'),
        );

        $this->assertSame(
            [$user->id => 'Marisol Quintero (phase-one-user)'],
            $service->options('marisol.quintero@example.test'),
        );
    }

    public function test_formats_labels_for_full_clerical_and_username_variants(): void
    {
        $user = User::factory()->create([
            'username' => 'label-user',
            'first_name' => 'Avery',
            'last_name' => 'Stone',
        ]);

        $labels = resolve(UserOptionLabel::class);

        $this->assertSame('Avery Stone (label-user)', $labels->for($user));
        $this->assertSame('Stone, Avery (label-user)', $labels->for($user, UserOptionLabel::FormatClericalName));
        $this->assertSame('label-user', $labels->for($user, UserOptionLabel::FormatUsername, false));
    }

    public function test_for_nullable_returns_null_when_no_user_is_available(): void
    {
        $labels = resolve(UserOptionLabel::class);

        $this->assertNull($labels->forNullable(null));
    }

    public function test_search_respects_query_modifiers_and_can_disable_email_matching(): void
    {
        User::factory()->create([
            'username' => 'alpha-user',
            'first_name' => 'Alpha',
            'last_name' => 'Tester',
            'email' => 'alpha@example.test',
        ]);
        $expectedUser = User::factory()->create([
            'username' => 'beta-user',
            'first_name' => 'Beta',
            'last_name' => 'Tester',
            'email' => 'beta@example.test',
        ]);

        $service = resolve(UserSearch::class);

        $results = $service->search(
            'tester',
            modifyQuery: function (UserBuilder $query): void {
                $query->where('username', 'beta-user');
            },
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($expectedUser));
        $this->assertSame([], $service->options('alpha@example.test', includeEmail: false));
    }

    public function test_apply_leaves_the_query_unchanged_when_the_search_is_blank(): void
    {
        User::factory()->count(2)->create();

        $service = resolve(UserSearch::class);
        $query = User::query();

        $service->apply($query, '   ');

        $this->assertSame(User::count(), $query->count());
    }

    public function test_apply_to_relation_supports_and_and_or_query_booleans(): void
    {
        $matchingUser = User::factory()->create([
            'username' => 'relation-match',
            'first_name' => 'Mina',
            'last_name' => 'Stone',
        ]);
        $nonMatchingUser = User::factory()->create([
            'username' => 'relation-miss',
            'first_name' => 'Noah',
            'last_name' => 'Fields',
        ]);
        $matchingRecord = UserLoginRecord::factory()->create([
            'user_id' => $matchingUser->id,
        ]);
        $otherRecord = UserLoginRecord::factory()->create([
            'user_id' => $nonMatchingUser->id,
        ]);

        $service = resolve(UserSearch::class);

        $andIds = $service->applyToRelation(
            UserLoginRecord::query(),
            'user',
            'Mina',
        )->pluck('id')->all();

        $orIds = $service->applyToRelation(
            UserLoginRecord::query()->whereKey($otherRecord->id),
            'user',
            'Mina',
            boolean: 'or',
        )->pluck('id')->all();

        $this->assertSame([$matchingRecord->id], $andIds);
        $this->assertEqualsCanonicalizing([$matchingRecord->id, $otherRecord->id], $orIds);
    }

    public function test_label_returns_null_for_blank_input_and_formats_found_users(): void
    {
        $user = User::factory()->create([
            'username' => 'lookup-user',
            'first_name' => 'Jordan',
            'last_name' => 'Vale',
        ]);

        $service = resolve(UserSearch::class);

        $this->assertNull($service->label(null));
        $this->assertSame('lookup-user', $service->label($user->id, UserOptionLabel::FormatUsername, false));
    }
}
