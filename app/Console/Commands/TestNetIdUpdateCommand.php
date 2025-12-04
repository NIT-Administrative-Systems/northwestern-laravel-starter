<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Core\Concerns\MocksEventHub;
use App\Domains\User\Enums\NetIdUpdateActionEnum;
use App\Domains\User\Listeners\ProcessNetIdUpdate;
use App\Http\Controllers\Webhooks\NetIdUpdateController;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;

use function Laravel\Prompts\select;

/**
 * Simulates webhooks for messages delivered to Northwestern Identity's **NetID Updates** topic.
 *
 * This command allows developers to test the NetID webhook integration without relying on
 * external Northwestern systems. It replicates the behavior of EventHub's delivery for
 * notifications by sending formatted webhook payloads to the application.
 *
 * @see NetIdUpdateController
 * @see ProcessNetIdUpdate
 */
class TestNetIdUpdateCommand extends Command implements PromptsForMissingInput
{
    use MocksEventHub;

    private const string QUEUE = 'etidentity.ldap.netid.term';

    protected $signature = 'netid:update:test
                           {netId       : The NetID to include in the message}
                           {action      : The NetID update action to simulate}
                           {--via-queue : Send via EventHub queue instead of directly to the HTTP webhook}';

    protected $description = 'Submit a fake NetID Update message.';

    public function handle(): int
    {
        $netId = (string) $this->argument('netId');
        $actionInput = (string) $this->argument('action');
        $viaQueue = (bool) $this->option('via-queue');

        $action = NetIdUpdateActionEnum::tryFrom($actionInput);

        if (! $action) {
            $valid = implode(', ', array_map(
                static fn (NetIdUpdateActionEnum $a) => $a->value,
                NetIdUpdateActionEnum::cases()
            ));

            $this->components->error("Invalid action [{$actionInput}].");
            $this->components->error('Valid actions: ' . $valid);

            return self::INVALID;
        }

        $payload = http_build_query([
            'netid' => $netId,
            'action' => $action->value,
        ]);

        $transport = $viaQueue ? 'EventHub Queue' : 'HTTP Webhook';

        $this->components->info('Preparing fake NetID update…');
        $this->components->twoColumnDetail('NetID', $netId);
        $this->components->twoColumnDetail('Action', $action->value);
        $this->components->twoColumnDetail('Transport', $transport);
        $this->components->twoColumnDetail('Queue', self::QUEUE);

        $this->send(self::QUEUE, $payload, $viaQueue);

        $this->components->success('Fake NetID update submitted.');

        return self::SUCCESS;
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'netId' => 'Enter a NetID',
            'action' => fn () => select(
                'Select an action',
                array_map(
                    static fn (NetIdUpdateActionEnum $a) => $a->value,
                    NetIdUpdateActionEnum::cases()
                )
            ),
        ];
    }
}
