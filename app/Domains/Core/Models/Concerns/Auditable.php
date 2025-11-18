<?php

declare(strict_types=1);

namespace App\Domains\Core\Models\Concerns;

use App\Domains\Core\ValueObjects\ApiRequestContext;
use App\Domains\User\Models\Audit;
use Exception;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Livewire\Livewire;

trait Auditable
{
    use \OwenIt\Auditing\Auditable;

    /**
     * {@inheritDoc}
     *
     * Before storing an {@see Audit} record, perform any necessary transformations on the audit data.
     *
     * {@link https://laravel-auditing.com/guide/audit-transformation.html}
     *
     * @param  array<string, mixed>  $data  The audit data to be transformed.
     * @return array<string, mixed> The transformed audit data.
     */
    public function transformAudit(array $data): array
    {
        // Attach an API request trace ID if available
        if ($traceId = Context::get(ApiRequestContext::TRACE_ID)) {
            $data['trace_id'] = $traceId;
        }

        // Attach impersonation information if available
        $data['impersonator_user_id'] = app('impersonate')->getImpersonatorId();

        // Modify the URL for Livewire requests to include component information
        if (Str::contains(
            $data['url'] ?? '',
            Livewire::getUpdateUri()
        ) && $component = $this->extractLivewireComponentName()) {
            $data['url'] .= '#' . $component;
        }

        return $data;
    }

    /**
     * Extract the Livewire component name from the request (if any).
     */
    protected function extractLivewireComponentName(): ?string
    {
        // Check if this is a Livewire request with components data
        $livewireSnapshot = request('components.0.snapshot');

        if (! $livewireSnapshot) {
            return null;
        }

        try {
            // The snapshot is a JSON string, so we need to decode it
            $decodedSnapshot = json_decode((string) $livewireSnapshot, true, 512, JSON_THROW_ON_ERROR);

            return data_get($decodedSnapshot, 'memo.name');
        } catch (Exception) {
            // Extracting the component name isn't critical, we can ignore any errors and return null

            return null;
        }
    }
}
