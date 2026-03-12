<?php

declare(strict_types=1);

namespace App\Domains\Core\Health;

use Northwestern\SysDev\SOA\DirectorySearch;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class DirectorySearchCheck extends Check
{
    protected ?string $label = 'NU Directory Search API';

    public function run(): Result
    {
        $healthResult = Result::make();

        $netId = config('nusoa.directorySearch.healthCheckNetid');

        if (blank($netId)) {
            return $healthResult
                ->warning()
                ->shortSummary('Configuration missing')
                ->notificationMessage('Health check skipped: Test NetID not configured');
        }

        $directorySearch = resolve(DirectorySearch::class);
        $directoryLookup = $directorySearch->lookupByNetId($netId, 'basic');

        if (filled($directorySearch->getLastError())) {
            return $healthResult
                ->failed('API error')
                ->notificationMessage("Directory Search API error - {$directorySearch->getLastError()}");
        }

        if ($directoryLookup === false || blank($directoryLookup)) {
            return $healthResult
                ->failed('Empty response received')
                ->notificationMessage("Directory Search API returned no data for test NetID: {$netId}");
        }

        if (! data_get($directoryLookup, 'uid')) {
            return $healthResult
                ->failed('Invalid response structure')
                ->notificationMessage("Directory Search API response missing required 'uid' field for NetID: {$netId}");
        }

        return $healthResult
            ->ok();
    }
}
