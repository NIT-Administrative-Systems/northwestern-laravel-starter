<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Core\Services;

use App\Domains\Core\Services\DateTimeFormatter;
use App\Domains\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DateTimeFormatter::class)]
class DateTimeFormatterTest extends TestCase
{
    private DateTimeFormatter $formatter;

    private User $user;

    private string $defaultFormat = 'M j, Y H:i:s T';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.datetime_display_format', $this->defaultFormat);

        $this->user = User::factory()->make(['timezone' => 'America/Los_Angeles']);

        $this->formatter = new DateTimeFormatter();

        Carbon::setTestNow(Carbon::parse('2024-01-01 10:00:00', 'UTC'));
    }

    public function test_datetime_returns_na_for_null_input(): void
    {
        $this->assertEquals('n/a', $this->formatter->datetime(null, $this->user));
    }

    public function test_datetime_returns_na_for_empty_string_input(): void
    {
        $this->assertEquals('n/a', $this->formatter->datetime('', $this->user));
    }

    public function test_datetime_formats_carbon_instance_with_default_format_and_user_timezone(): void
    {
        $datetime = Carbon::getTestNow();
        $expected = 'Jan 1, 2024 02:00:00 PST';

        $result = $this->formatter->datetime($datetime, $this->user);

        $this->assertEquals($expected, $result);
    }

    public function test_datetime_formats_string_instance_with_default_format_and_user_timezone(): void
    {
        $datetimeString = '2024-06-15 08:30:00';
        $expected = 'Jun 15, 2024 01:30:00 PDT';

        $result = $this->formatter->datetime($datetimeString, $this->user);

        $this->assertEquals($expected, $result);
    }

    public function test_datetime_formats_with_custom_format_and_user_timezone(): void
    {
        $this->user->timezone = 'Europe/London';

        $datetime = Carbon::getTestNow();
        $customFormat = 'Y/m/d H:i';
        $expected = '2024/01/01 10:00';

        $result = $this->formatter->datetime($datetime, $this->user, $customFormat);

        $this->assertEquals($expected, $result);
    }

    public function test_datetime_handles_different_user_timezone_correctly(): void
    {
        $this->user->timezone = 'Asia/Tokyo';

        $datetime = Carbon::getTestNow();
        $expected = 'Jan 1, 2024 19:00:00 JST';

        $result = $this->formatter->datetime($datetime, $this->user);

        $this->assertEquals($expected, $result);
    }

    public function test_build_datetime_directive_returns_correct_blade_php_string(): void
    {
        $directive = $this->formatter->buildDatetimeDirective();
        $expression = '$user->created_at';
        $expectedClass = '\\' . DateTimeFormatter::class;

        $expectedOutput = "<?php echo resolve({$expectedClass}::class)->datetime({$expression}, auth()->user()); ?>";

        $this->assertEquals($expectedOutput, $directive($expression));
    }
}
