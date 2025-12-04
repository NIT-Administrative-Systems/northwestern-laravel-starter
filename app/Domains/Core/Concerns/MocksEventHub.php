<?php

declare(strict_types=1);

namespace App\Domains\Core\Concerns;

use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Northwestern\SysDev\SOA\EventHub;
use Northwestern\SysDev\SOA\Routing\EventHubWebhookRegistration;

/**
 * Use this trait in console commands or tests to simulate a message being sent to EventHub on a specific queue.
 * By doing so, you can test the behavior of any application webhooks that are registered to that queue.
 */
trait MocksEventHub
{
    /**
     * Simulates sending a message to an EventHub queue and triggering the registered webhook.
     *
     * @param  string  $queue  The EventHub topic/queue name (e.g., 'etidentity.ldap.netid.term')
     * @param  string  $message  The raw message payload to send (typically URL-encoded or JSON)
     * @param  bool  $viaQueue  Whether to send via EventHub queue instead of direct HTTP (default: false)
     * @return mixed Returns TestResponse during PHPUnit tests, Response during console commands, or queue result when viaQueue=true
     *
     * @throws InvalidArgumentException When no webhook route is registered for the specified queue
     */
    protected function send(string $queue, string $message, bool $viaQueue = false): mixed
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

        $response = app()->make(\Illuminate\Contracts\Http\Kernel::class)->handle($request);

        if (app()->runningUnitTests()) {
            return TestResponse::fromBaseResponse($response);
        }

        return $response;
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
