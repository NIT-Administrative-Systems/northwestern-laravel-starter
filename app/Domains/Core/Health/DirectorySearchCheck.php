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
        $result = Result::make();

        $netId = config('nusoa.directorySearch.healthCheckNetid');

        if (blank($netId)) {
            return $result
                ->warning()
                ->shortSummary('Configuration missing')
                ->notificationMessage('Health check skipped: Test NetID not configured');
        }

        $directorySearch = resolve(DirectorySearch::class);
        $info = $directorySearch->lookupByNetId($netId, 'basic');

        if (filled($directorySearch->getLastError())) {
            return $result
                ->failed()
                ->shortSummary('API error')
                ->notificationMessage("Directory Search API error - {$directorySearch->getLastError()}")
                ->meta(['tested_netid' => $netId]);
        }

        if ($info === false || blank($info)) {
            return $result
                ->failed()
                ->shortSummary('Empty response received')
                ->notificationMessage("Directory Search API returned no data for test NetID: {$netId}")
                ->meta(['tested_netid' => $netId]);
        }

        if (! data_get($info, 'uid')) {
            return $result
                ->failed()
                ->shortSummary('Invalid response structure')
                ->notificationMessage("Directory Search API response missing required 'uid' field for NetID: {$netId}")
                ->meta(['tested_netid' => $netId]);
        }

        return $result
            ->ok()
            ->shortSummary('API operational')
            ->meta(['tested_netid' => $netId]);
    }
}
