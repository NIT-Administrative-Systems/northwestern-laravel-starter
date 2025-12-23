<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Directory;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\User\Actions\PersistUserWithUniqueUsername;
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
 * Finds or updates a user from the directory.
 *
 * This action performs a directory lookup to fetch a {@see User} based on a provided search value.
 * If a user is found, it dispatches a set of post-retrieval jobs either synchronously or asynchronously,
 * depending on the provided flag.
 */
readonly class FindOrUpdateUserFromDirectory
{
    public function __construct(
        protected DirectorySearch $directorySearch,
        protected SyncUserFromDirectory $directorySync,
        protected PersistUserWithUniqueUsername $persistUserWithUniqueUsername,
    ) {
        //
    }

    /**
     * Job(s) to execute after a {@see User} is successfully retrieved.
     *
     * These are commonly longer-running asynchronous tasks that are
     * not critical to the immediate retrieval of the user.
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
     * @param  string  $searchValue  NetID, email address, or employee ID.
     * @param  bool  $immediate  Whether to synchronously run {@see self::postRetrievalJobs()} or dispatch them to the queue
     *                           after a {@see User} is retrieved.
     *
     * @throws BadDirectoryEntry
     * @throws InvalidArgumentException
     */
    public function __invoke(string $searchValue, bool $immediate = false): ?User
    {
        if (blank($searchValue = trim($searchValue))) {
            throw new InvalidArgumentException('Search value cannot be empty');
        }

        $user = $this->fetchAndProcessUser($searchValue);

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
     * @throws BadDirectoryEntry
     */
    protected function fetchAndProcessUser(string $searchValue): ?User
    {
        $searchType = DirectorySearchType::fromSearchValue($searchValue);

        $existingUser = $this->findExistingUser($searchValue, $searchType);

        if ($existingUser?->auth_type === AuthTypeEnum::API) {
            return null;
        }

        $directoryData = $this->directorySearch->lookup($searchValue, $searchType->value, 'basic');

        if ($this->isDirectoryDataInvalid($directoryData)) {
            return $this->handleInvalidDirectoryData($existingUser, $searchValue, $directoryData);
        }

        return $this->syncAndPersistUser($directoryData, $searchType);
    }

    /**
     * Based on the search value and type, attempts to find an existing user in the database.
     */
    private function findExistingUser(string $searchValue, DirectorySearchType $searchType): ?User
    {
        return match ($searchType) {
            DirectorySearchType::EMAIL => User::whereEmailEquals($searchValue)->first(),
            DirectorySearchType::NETID => User::whereUsernameEquals($searchValue)->first(),
            DirectorySearchType::EMPLOYEE_ID => User::where('employee_id', $searchValue)->first(),
        };
    }

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
    private function isDirectoryDataInvalid(array|false|null $directoryData): bool
    {
        return ! $directoryData || ! Arr::get($directoryData, 'eduPersonPrimaryAffiliation') || ! Arr::get($directoryData, 'mail');
    }

    /**
     * Handles cases where directory data is missing or incomplete.
     *
     * If an existing {@see User} is found, and they authenticate via SSO, the user is flagged as having an
     * inactive NetID and the directory sync failure timestamp is recorded. This allows the application to
     * treat the account as effectively deprovisioned while still retaining local history.
     *
     * If no existing user is found, a {@see BadDirectoryEntry} exception is thrown so that callers can surface
     * or log the invalid directory state.
     *
     * @throws BadDirectoryEntry
     */
    private function handleInvalidDirectoryData(?User $existingUser, string $searchValue, array|false|null $directoryData): User
    {
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

    /**
     * Synchronizes directory data into a {@see User} model and persists it.
     */
    private function syncAndPersistUser(array $directoryData, DirectorySearchType $searchType): User
    {
        $user = ($searchType === DirectorySearchType::EMAIL)
            ? User::firstExistingByEmailOrNewSso($directoryData['mail'])
            : User::firstExistingSsoByNetIdOrNew($directoryData['uid']);

        $user = ($this->directorySync)($user, $directoryData);

        return ($this->persistUserWithUniqueUsername)($user);
    }

    /**
     * Returns the last error from Directory Search.
     */
    public function getLastError(): ?string
    {
        return $this->directorySearch->getLastError();
    }
}
