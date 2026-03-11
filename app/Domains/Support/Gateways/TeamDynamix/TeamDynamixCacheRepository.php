<?php

declare(strict_types=1);

namespace App\Domains\Support\Gateways\TeamDynamix;

use App\Domains\Support\Exceptions\TdxLookupFailed;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Northwestern\Sysdev\TeamDynamix\Laravel\TeamDynamixService;
use Throwable;

/**
 * Cached lookups for TeamDynamix metadata IDs (types, forms, statuses, etc.).
 *
 * TDX requires numeric IDs for ticket creation parameters, but they are only
 * available by name through API list endpoints. This repository caches those
 * lookups for one week to avoid repeated API calls on ticket submission.
 *
 * @phpstan-type TdxRecord array{ID: positive-int, Name: non-empty-string, ...}
 *
 * @see TeamDynamixGateway
 */
class TeamDynamixCacheRepository
{
    protected CarbonInterval $cacheFor;

    public function __construct(
        protected TeamDynamixService $tdxApiManager,
    ) {
        $this->cacheFor = CarbonInterval::week();
    }

    /** @return positive-int */
    public function findTicketTypeId(string $name): int
    {
        return Cache::remember(
            sprintf('tdx/ticket/typeId/%s', Str::slug($name)),
            $this->cacheFor,
            fn () => $this->find('Ticket Type', $this->tdxApiManager->ticketType()->all()->body, $name),
        );
    }

    /** @return positive-int */
    public function findTicketFormTypeId(string $name): int
    {
        return Cache::remember(
            sprintf('tdx/ticket/formId/%s', Str::slug($name)),
            $this->cacheFor,
            fn () => $this->find('Form Type', $this->tdxApiManager->ticket()->allForms()->body, $name),
        );
    }

    /** @return positive-int */
    public function findTicketStatusId(string $name): int
    {
        return Cache::remember(
            sprintf('tdx/ticket/statusId/%s', Str::slug($name)),
            $this->cacheFor,
            fn () => $this->find('Ticket Status', $this->tdxApiManager->ticketStatus()->all()->body, $name),
        );
    }

    /** @return positive-int */
    public function findTicketPriorityId(string $name): int
    {
        return Cache::remember(
            sprintf('tdx/ticket/priorityId/%s', Str::slug($name)),
            $this->cacheFor,
            fn () => $this->find('Ticket Priority', $this->tdxApiManager->ticketPriority()->all()->body, $name),
        );
    }

    /** @return positive-int */
    public function findServiceId(string $name): int
    {
        return Cache::remember(
            sprintf('tdx/service/id/%s', Str::slug($name)),
            $this->cacheFor,
            fn () => $this->find('Service', $this->tdxApiManager->serviceCatalog()->all()->body, $name),
        );
    }

    /**
     * Search a TDX API list response for a record matching the given name.
     *
     * @param  string  $apiName  Human-readable label for error reporting (e.g., "Ticket Type").
     * @param  string  $rawBody  Raw JSON response body from the TDX API list endpoint.
     * @param  string  $value  The name to match (case-insensitive).
     * @return positive-int The numeric ID of the matching record.
     *
     * @throws TdxLookupFailed|Throwable If no record with the given name is found.
     */
    private function find(string $apiName, string $rawBody, string $value): int
    {
        /** @var array<int, TdxRecord> $decoded */
        $decoded = json_decode($rawBody, true);
        $types = collect($decoded);

        $type = $types->filter(fn (array $type) => strtolower($type['Name']) === strtolower($value))->first();
        throw_unless($type, TdxLookupFailed::for($apiName, $value));

        return $type['ID'];
    }
}
