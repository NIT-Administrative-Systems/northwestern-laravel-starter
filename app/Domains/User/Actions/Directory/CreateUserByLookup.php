<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Directory;

use App\Domains\User\Actions\PersistUserWithUniqueUsername;
use App\Domains\User\Enums\AuthTypeEnum;
use App\Domains\User\Enums\DirectorySearchType;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Jobs\DownloadWildcardPhotoJob;
use App\Domains\User\Models\User;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Northwestern\SysDev\SOA\DirectorySearch;

/**
 * Performs a directory lookup to fetch a {@see User} based on a provided search value. If a user is found,
 * it dispatches a set of post-retrieval jobs either synchronously or asynchronously, depending on the
 * provided flag.
 */
readonly class CreateUserByLookup
{
    public function __construct(
        protected DirectorySearch $directorySearch,
        protected SyncUserFromDirectory $directorySync,
        protected PersistUserWithUniqueUsername $persistUserWithUniqueUsername,
    ) {
        //
    }

    /**
     * @param  string  $searchValue  NetID, email address, or employee ID.
     * @param  bool  $immediate  Whether to synchronously run {@see self::postRetrievalJobs()} or dispatch them to the queue
     *                           after a {@see User} is retrieved.
     *
     * @throws BadDirectoryEntry
     * @throws InvalidArgumentException
     */
    public function __invoke(string $searchValue, bool $immediate = false): ?User
    {
        if (blank(trim($searchValue))) {
            throw new InvalidArgumentException('Search value cannot be empty');
        }

        $user = $this->fetchUser($searchValue);

        if (! $user instanceof User) {
            return null;
        }

        foreach ($this->postRetrievalJobs() as $jobClass) {
            if ($immediate) {
                // A failed synchronous post-retrieval job should not break the flow of user retrieval.
                try {
                    dispatch_sync(new $jobClass($user));
                } catch (Exception $e) {
                    report($e);
                }
            } else {
                dispatch(new $jobClass($user));
            }
        }

        return $user;
    }

    /**
     * Job(s) to execute after a {@see User} is successfully retrieved.
     *
     * @return list<class-string<ShouldQueue>>
     */
    private function postRetrievalJobs(): array
    {
        $jobs = [
            // Add any custom post-retrieval jobs here
        ];

        if (config('platform.wildcard_photo_sync')) {
            $jobs[] = DownloadWildcardPhotoJob::class;
        }

        return $jobs;
    }

    /**
     * Returns the last error from Directory Search.
     */
    public function getLastError(): ?string
    {
        return $this->directorySearch->getLastError();
    }

    /**
     * @param  string  $searchValue  NetID or email address.
     *
     * @throws BadDirectoryEntry
     */
    protected function fetchUser(string $searchValue): ?User
    {
        $searchType = DirectorySearchType::fromSearchValue($searchValue);

        $existingUser = match ($searchType) {
            DirectorySearchType::EMAIL => User::query()
                ->whereEmailEquals($searchValue)
                ->first(),
            DirectorySearchType::NETID => User::query()
                ->whereUsernameEquals($searchValue)
                ->first(),
            DirectorySearchType::EMPLOYEE_ID => User::query()
                ->where('employee_id', '=', trim($searchValue))
                ->first(),
        };

        if ($existingUser?->auth_type === AuthTypeEnum::API) {
            return null;
        }

        $directoryData = $this->directorySearch->lookup(trim($searchValue), $searchType->value, 'basic');

        /**
         *  Certain directory entries can come back with missing or incomplete data. This is often the result of
         *  a previous Northwestern employee or student who is no longer active. If we couldn't at least find
         *  an existing {@see User} record, throw an exception.
         *
         * This commonly affects:
         * - Unmatriculated students
         * - Certain affiliates
         * - Students whose admission was canceled/revoked
         */
        if (! $directoryData ||
            ! Arr::get($directoryData, 'eduPersonPrimaryAffiliation') ||
            ! Arr::get($directoryData, 'mail')
        ) {
            if ($existingUser) {
                if ($existingUser->auth_type === AuthTypeEnum::SSO) {
                    $existingUser->update([
                        'netid_inactive' => true,
                        'directory_sync_last_failed_at' => now(),
                    ]);
                }

                return $existingUser;
            }

            throw new BadDirectoryEntry(
                netId: $directoryData['uid'] ?? $searchValue,
                directoryData: $directoryData ?: []
            );
        }

        // Synchronize user attributes with directory data (does not save yet)
        if ($searchType === DirectorySearchType::EMAIL) {
            $user = ($this->directorySync)(
                User::firstExistingByEmailOrNewSso($directoryData['mail']),
                $directoryData,
            );
        } else {
            $user = ($this->directorySync)(
                User::firstExistingSsoByNetIdOrNew($directoryData['uid']),
                $directoryData,
            );
        }

        return ($this->persistUserWithUniqueUsername)($user);
    }
}
