<?php

declare(strict_types=1);

namespace App\Domains\Core\Concerns;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Northwestern\SysDev\SOA\EventHub;
use Northwestern\SysDev\SOA\Routing\EventHubWebhookRegistration;

/**
 * Use this trait in console commands or tests to simulate a message being sent to EventHub on a specific queue.
 * By doing so, you can test the behavior of any application webhooks that are registered to that queue.
 *
 * @phpstan-ignore trait.unused
 */
trait MocksEventHub
{
    protected function send(string $queue, string $message, bool $viaQueue): mixed
    {
        if ($viaQueue) {
            return resolve(EventHub\Queue::class)
                ->sendTestMessage($queue, $message, 'text/plain');
        }

        $route = $this->getRouteForQueue($queue);

        if ($route === null) {
            throw new InvalidArgumentException("No webhook route found for queue: {$queue}");
        }

        $headers = [
            'Content-Type' => 'text/plain',
            ...$this->makeHmacHeader($message),
        ];

        $request = Request::create($route, 'POST', [], [], [], [], $message);
        $request->headers->add($headers);

        return app()->make(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
    }

    /**
     * Get the route for the specified queue from registered webhooks.
     */
    private function getRouteForQueue(string $queue): ?string
    {
        $hooks = resolve(EventHubWebhookRegistration::class)->getHooks();

        foreach ($hooks as $hook) {
            $hookData = $hook->toArray();

            if ($hookData['topicName'] === $queue) {
                return str_replace(url('/'), '', $hookData['endpoint']);
            }
        }

        return null;
    }

    /**
     * Create HMAC header for webhook verification.
     *
     * @return array<string, string>
     */
    private function makeHmacHeader(string $message): array
    {
        $sharedSecret = config('nusoa.eventHub.hmacVerificationSharedSecret') ?? '';
        $algorithm = config('nusoa.eventHub.hmacVerificationAlgorithmForPHPHashHmac');
        $headerName = config('nusoa.eventHub.hmacVerificationHeader');

        $hmacHash = hash_hmac((string) $algorithm, $message, $sharedSecret, true);
        $encodedHash = base64_encode($hmacHash);

        return [$headerName => $encodedHash];
    }
}
