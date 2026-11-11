<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Auth\Jobs;

use App\Domains\Auth\Jobs\SendLoginCodeEmailJob;
use App\Domains\Auth\LoginCodes;
use App\Domains\Auth\Mail\LoginCodeMail;
use App\Domains\Auth\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendLoginCodeEmailJob::class)]
final class SendLoginCodeEmailJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::now()->startOfSecond());
        Mail::fake();
    }

    public function test_job_sends_email_and_updates_email_sent_at(): void
    {
        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $job = new SendLoginCodeEmailJob(
            $challenge->id,
            Crypt::encryptString('123456')
        );

        $job->handle(resolve(LoginCodes::class));

        Mail::assertSent(LoginCodeMail::class, fn (LoginCodeMail $mail) => $mail->hasTo($challenge->email));

        $this->assertTrue($challenge->fresh()->email_sent_at->eq(now()));
    }

    // The link lets a code issued outside the sign-in page (by an administrator, say) be entered.
    public function test_email_links_to_the_code_step_for_its_challenge(): void
    {
        $challenge = LoginChallenge::create([
            'email' => 'test@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        new SendLoginCodeEmailJob($challenge->id, Crypt::encryptString('123456'))->handle(resolve(LoginCodes::class));

        Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use ($challenge) {
            parse_str((string) parse_url($mail->signInUrl, PHP_URL_QUERY), $query);

            return str_starts_with($mail->signInUrl, url('/app/login/email'))
                && Crypt::decryptString($query[LoginCodes::LINK_PARAMETER]) === (string) $challenge->id;
        });
    }

    public function test_job_skips_when_challenge_missing_or_already_sent(): void
    {
        $missingJob = new SendLoginCodeEmailJob(999, Crypt::encryptString('000000'));
        $missingJob->handle(resolve(LoginCodes::class));

        Mail::assertNothingSent();

        $challenge = LoginChallenge::create([
            'email' => 'sent@example.com',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
            'email_sent_at' => now(),
        ]);

        $job = new SendLoginCodeEmailJob(
            $challenge->id,
            Crypt::encryptString('123456')
        );

        $job->handle(resolve(LoginCodes::class));

        Mail::assertNothingSent();
        $this->assertTrue($challenge->fresh()->email_sent_at->eq(now()));
    }

    // A queue backlog can deliver a code after a newer one replaced it, or after it expired.
    public function test_a_code_that_can_no_longer_be_used_is_not_sent(): void
    {
        $challenges = [
            'expired' => ['expires_at' => now()->subSecond()],
            'used' => ['expires_at' => now()->addMinutes(10), 'consumed_at' => now()],
            'locked' => ['expires_at' => now()->addMinutes(10), 'locked_until' => now()->addMinutes(15)],
        ];

        foreach ($challenges as $state => $attributes) {
            $challenge = LoginChallenge::create([
                'email' => "{$state}@example.com",
                'code_hash' => Hash::make('123456'),
                ...$attributes,
            ]);

            new SendLoginCodeEmailJob($challenge->id, Crypt::encryptString('123456'))->handle(resolve(LoginCodes::class));

            $this->assertNull($challenge->fresh()->email_sent_at, "The {$state} code was sent.");
        }

        Mail::assertNothingSent();
    }
}
