<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Core\Data;

use App\Domains\Core\Data\Preferences;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preferences::class)]
final class PreferencesTest extends TestCase
{
    public function test_missing_values_fall_back_to_defaults(): void
    {
        $preferences = ExamplePreferences::fromArray([]);

        $this->assertTrue($preferences->emailDigest);
        $this->assertSame(ExampleFrequency::Weekly, $preferences->frequency);
        $this->assertSame(10, $preferences->pageSize);
        $this->assertNull($preferences->nickname);
    }

    public function test_stored_values_are_read_with_their_types(): void
    {
        $preferences = ExamplePreferences::fromArray([
            'emailDigest' => false,
            'frequency' => 'daily',
            'pageSize' => 25,
            'nickname' => 'Willie',
        ]);

        $this->assertFalse($preferences->emailDigest);
        $this->assertSame(ExampleFrequency::Daily, $preferences->frequency);
        $this->assertSame(25, $preferences->pageSize);
        $this->assertSame('Willie', $preferences->nickname);
    }

    // The JSON outlives the code: removed preferences and bad values must not break reading.
    public function test_unknown_keys_are_dropped_and_wrong_types_use_the_default(): void
    {
        $preferences = ExamplePreferences::fromArray([
            'removedLongAgo' => true,
            'emailDigest' => 'yes',
            'frequency' => 'hourly',
            'pageSize' => '25',
        ]);

        $this->assertSame([
            'emailDigest' => true,
            'frequency' => 'weekly',
            'pageSize' => 10,
            'nickname' => null,
        ], $preferences->toArray());
    }

    public function test_a_nullable_preference_can_be_cleared(): void
    {
        $preferences = ExamplePreferences::fromArray(['nickname' => 'Willie'])->with(['nickname' => null]);

        $this->assertNull($preferences->nickname);
    }

    public function test_with_changes_only_the_given_preferences(): void
    {
        $preferences = ExamplePreferences::fromArray(['pageSize' => 50])->with(['frequency' => ExampleFrequency::Daily]);

        $this->assertSame(50, $preferences->pageSize);
        $this->assertSame(ExampleFrequency::Daily, $preferences->frequency);
    }

    public function test_the_cast_reads_json_and_writes_every_preference(): void
    {
        $cast = ExamplePreferences::castUsing([]);
        $model = new class extends Model
        {
        };

        $read = $cast->get($model, 'preferences', '{"pageSize":50,"removedLongAgo":true}', []);
        $this->assertInstanceOf(ExamplePreferences::class, $read);
        $this->assertSame(50, $read->pageSize);

        $this->assertSame(
            '{"emailDigest":true,"frequency":"weekly","pageSize":50,"nickname":null}',
            $cast->set($model, 'preferences', $read, []),
        );
        $this->assertSame(
            '{"emailDigest":false,"frequency":"weekly","pageSize":10,"nickname":null}',
            $cast->set($model, 'preferences', ['emailDigest' => false], []),
        );
    }

    public function test_the_cast_reads_an_empty_or_unreadable_column_as_defaults(): void
    {
        $cast = ExamplePreferences::castUsing([]);
        $model = new class extends Model
        {
        };

        $this->assertEquals(ExamplePreferences::fromArray([]), $cast->get($model, 'preferences', null, []));
        $this->assertEquals(ExamplePreferences::fromArray([]), $cast->get($model, 'preferences', 'not json', []));
    }

    public function test_the_cast_refuses_other_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $model = new class extends Model
        {
            protected $casts = ['preferences' => ExamplePreferences::class];
        };

        $model->setAttribute('preferences', 'daily');
    }
}

enum ExampleFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
}

final readonly class ExamplePreferences extends Preferences
{
    public function __construct(
        public bool $emailDigest = true,
        public ExampleFrequency $frequency = ExampleFrequency::Weekly,
        public int $pageSize = 10,
        public ?string $nickname = null,
    ) {
    }
}
