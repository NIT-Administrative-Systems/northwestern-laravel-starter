<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Jobs;

use App\Domains\User\Jobs\SendLoginCodeEmailJob;
use App\Domains\User\Mail\LoginCodeNotification;
use App\Domains\User\Models\LoginChallenge;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SendLoginCodeEmailJob::class)]
class SendLoginCodeEmailJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow(CarbonImmutable::now()->startOfSecond());
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

        $job->handle();

        Mail::assertSent(LoginCodeNotification::class, function (LoginCodeNotification $mail) use ($challenge) {
            return $mail->hasTo($challenge->email);
        });

        $this->assertTrue($challenge->fresh()->email_sent_at->eq(now()));
    }

    public function test_job_skips_when_challenge_missing_or_already_sent(): void
    {
        $missingJob = new SendLoginCodeEmailJob(999, Crypt::encryptString('000000'));
        $missingJob->handle();

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

        $job->handle();

        Mail::assertNothingSent();
        $this->assertTrue($challenge->fresh()->email_sent_at->eq(now()));
    }
}
