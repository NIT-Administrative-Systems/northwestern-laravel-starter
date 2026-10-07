# EventHub

EventHub is Northwestern’s enterprise messaging platform, combining an Amazon MQ (Apache ActiveMQ) message broker with a RESTful Messaging Center API. Applications communicate through **topics** (one-to-many message distribution), **queues** (per-consumer message storage), and **webhooks** (automated HTTP delivery).

EventHub supports many event types across Northwestern: identity lifecycle changes, student record updates, employee data changes, and custom application events. Access to specific topics is managed through the [API Service Registry](https://apiserviceregistry.northwestern.edu/).

The starter includes a webhook endpoint that listens for **NetID status changes** from the `etidentity.ldap.netid.term` topic (deactivation, deprovisioning, and security holds) and adjusts user access in response.

The EventHub integration is provided by the [`northwestern-sysdev/laravel-soa`](https://github.com/NIT-Administrative-Systems/SysDev-laravel-soa) package, which handles webhook route registration, HMAC signature verification, and queue integration.

## How It Works

1. **EventHub sends a webhook**

   When a NetID is deactivated, deprovisioned, or placed on security hold, Northwestern’s EventHub pushes a signed HTTP POST to the application’s webhook endpoint.

2. **HMAC verification**

   The `eventhub_hmac` middleware validates the `X-HMAC-Signature` header against the shared secret, rejecting tampered or unsigned requests.

3. **Payload parsing**

   `NetIdUpdateController` reads the URL-encoded body (`netid=abc123&action=deactivate`) and constructs a `NetIdUpdated` event object, which validates the action against known values.

4. **User lookup**

   The controller checks if the NetID belongs to an SSO user. Non-SSO users (local auth, API users) are ignored since their accounts are managed independently.

5. **Asynchronous processing**

   The `NetIdUpdated` event is dispatched, and `ProcessNetIdUpdate` handles it on the queue:

   * All roles except `Northwestern User` are removed
   * The user is marked as `netid_inactive = true`

***

## Customizing Deprovisioning Logic

The `ProcessNetIdUpdate` listener handles the default deprovisioning behavior (role removal and marking the NetID inactive) inside a database transaction. To add your own business logic, such as sending notifications or archiving data, edit the transaction block in the listener. You don’t need to revoke API credentials here: once `netid_inactive` is set, the API refuses the person’s tokens on every request, and `oauth:revoke-ineligible` marks them revoked.

app/Domains/User/Listeners/ProcessNetIdUpdate.php

```php
DB::transaction(static function () use ($event) {
    $user = User::query()
        ->sso()
        ->lockForUpdate()
        ->with('roles')
        ->firstWhere('username', $event->netId);


    if (! $user) {
        return;
    }


    $user->roles
        ->reject(fn (Role $role) => $role->name === SystemRole::NorthwesternUser->value)
        ->whenNotEmpty(fn ($roles) => $user->removeRoleWithAudit(
            roles: $roles->all(),
            origin: RoleModificationOrigin::NetIdStatusChange,
            context: ['netid_action' => $event->action->value]
        ));


    $user->update(['netid_inactive' => true]);


    // Add custom deprovisioning logic here, if needed
});
```

> **Tip**
>
> Everything in the transaction block runs on the queue, so longer-running operations won’t block the webhook response. If you need logic that should run outside the transaction, add it after the `DB::transaction()` call in the `handle()` method.

***

## Enabling the Webhook

The EventHub webhook route is commented out by default in `routes/api.php`. To enable it:

routes/api.php

```php
Route::middleware(['eventhub_hmac'])->prefix('eventhub')->group(function () {
    Route::post('netid-update', NetIdUpdateController::class)
        ->eventHubWebhook('etidentity.ldap.netid.term')
        ->name('netid-update');
});
```

The `->eventHubWebhook()` macro registers the route with EventHub’s webhook discovery system and associates it with the `etidentity.ldap.netid.term` topic. The `eventhub_hmac` middleware ensures all incoming requests carry a valid HMAC signature.

> **Caution**
>
> After uncommenting the route, you must subscribe to the `etidentity.ldap.netid.term` topic and register your application’s webhook URL through the [API Service Registry](https://apiserviceregistry.northwestern.edu/). Then run `php artisan eventhub:webhook:configure` to register the webhook with EventHub. The `laravel-soa` package also provides `eventhub:webhook:status`, `eventhub:webhook:toggle`, `eventhub:queue:status`, `eventhub:topic:status` and `eventhub:dlq:restore-messages`. See the [package documentation](https://nit-administrative-systems.github.io/SysDev-laravel-soa/) for details.

***

## Testing

The `MocksEventHub` trait allows you to send synthetic webhook payloads through the HTTP kernel with valid HMAC signatures in tests and Artisan commands. It finds the webhook route by topic, so the route must be registered. While it is still commented out in `routes/api.php`, register it in `setUp()`, as `tests/Feature/Domains/User/Http/Controllers/Webhooks/NetIdUpdateControllerTest.php` does:

```php
use App\Domains\Core\Concerns\MocksEventHub;
use App\Domains\User\Http\Controllers\Webhooks\NetIdUpdateController;
use Illuminate\Support\Facades\Route;


class MyTest extends TestCase
{
    use MocksEventHub;


    protected function setUp(): void
    {
        parent::setUp();


        Route::post('netid-update', NetIdUpdateController::class)
            ->eventHubWebhook('etidentity.ldap.netid.term')
            ->name('netid-update');
    }


    public function test_handles_netid_deactivation(): void
    {
        $user = User::factory()->create([
            'username' => 'abc123',
            'auth_type' => AuthType::SSO,
        ]);


        $this->send(
            queue: 'etidentity.ldap.netid.term',
            message: 'netid=abc123&action=deactivate',
        );


        $user->refresh();
        $this->assertTrue($user->netid_inactive);
    }
}
```

The trait reads HMAC configuration from `config/nusoa.php` to generate the correct signature header, matching the verification the `eventhub_hmac` middleware performs.

***

## Environment Variables

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/#prop-event-hub-base-url)`EVENT_HUB_BASE_URL`Required

EventHub API base URL

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/#prop-event-hub-api-key)`EVENT_HUB_API_KEY`Required

Apigee API key for EventHub

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/#prop-event-hub-hmac-verification-shared-secret)`EVENT_HUB_HMAC_VERIFICATION_SHARED_SECRET`Required

Shared secret for HMAC signature verification

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/#prop-event-hub-hmac-verification-header)`EVENT_HUB_HMAC_VERIFICATION_HEADER``X-HMAC-Signature`

HTTP header containing the HMAC signature

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/#prop-event-hub-hmac-verification-algorithm-type-registration)`EVENT_HUB_HMAC_VERIFICATION_ALGORITHM_TYPE_REGISTRATION``HmacSHA256`

Algorithm name sent to EventHub during registration

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/#prop-event-hub-hmac-verification-algorithm-type-php)`EVENT_HUB_HMAC_VERIFICATION_ALGORITHM_TYPE_PHP``sha256`

PHP `hash_hmac` algorithm name

[#](https://laravel-starter.entapp.northwestern.edu/northwestern-integrations/eventhub/#prop-event-hub-mock-enabled)`EVENT_HUB_MOCK_ENABLED``true (local)`

Enable mock mode for local development
