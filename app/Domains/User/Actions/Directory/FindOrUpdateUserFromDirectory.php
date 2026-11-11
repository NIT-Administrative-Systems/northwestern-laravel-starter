<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Directory;

use App\Domains\Auth\Enums\AuthType;
use App\Domains\User\Actions\PersistUserWithUniqueUsername;
use App\Domains\User\Enums\DirectorySearchType;
use App\Domains\User\Exceptions\BadDirectoryEntry;
use App\Domains\User\Jobs\DownloadWildcardPhotoJob;
use App\Domains\User\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use Northwestern\SysDev\SOA\DirectorySearch;
use Throwable;

/**
 * Creates or updates someone's account from their Northwestern Directory entry.
 *
 * It then runs the post-retrieval jobs, on the queue or, when asked, right away.
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
     * @param  non-empty-string  $searchValue  NetID, email address, or employee ID.
     * @param  bool  $immediate  Run the post-retrieval jobs now rather than on the queue
     *
     * @throws BadDirectoryEntry
     * @throws InvalidArgumentException
     */
    public function __invoke(string $searchValue, bool $immediate = false): ?User
    {
        if (blank($searchValue = trim($searchValue))) {
            throw new InvalidArgumentException('Search value cannot be empty');
        }

        $user = $this->lookupAndSyncUserFromDirectory($searchValue);

        if (! $user instanceof User) {
            return null;
        }

        foreach ($this->postRetrievalJobs() as $jobClass) {
            if ($immediate) {
                // A failed post-retrieval job shouldn't stop the user being retrieved.
                try {
                    dispatch_sync(new $jobClass($user));
                } catch (Throwable $e) {
                    report($e);
                }
            } else {
                dispatch(new $jobClass($user));
            }
        }

        return $user;
    }

    /**
     * Get the last error from Directory Search.
     */
    public function getLastError(): ?string
    {
        return $this->directorySearch->getLastError();
    }

    /**
     * @throws BadDirectoryEntry
     */
    protected function lookupAndSyncUserFromDirectory(string $searchValue): ?User
    {
        $searchType = DirectorySearchType::fromSearchValue($searchValue);

        $existingUser = $this->findExistingUser($searchValue, $searchType);

        if ($existingUser?->auth_type === AuthType::API) {
            return null;
        }

        $directoryData = $this->directorySearch->lookup($searchValue, $searchType->value, 'basic');

        if ($this->isDirectoryDataInvalid($directoryData)) {
            return $this->resolveInvalidDirectoryEntry($existingUser, $searchValue, $directoryData);
        }

        return $this->syncAndPersistUser($directoryData, $searchType);
    }

    /**
     * Find the account the search value already belongs to, if any.
     */
    private function findExistingUser(string $searchValue, DirectorySearchType $searchType): ?User
    {
        // Selecting the query (rather than `return match`) keeps the match line
        // attributable by the coverage driver; see php-code-coverage#1257.
        $query = match ($searchType) {
            DirectorySearchType::Email => User::withTrashed()->whereEmailEquals($searchValue),
            DirectorySearchType::NetId => User::withTrashed()->whereUsernameEquals($searchValue),
            DirectorySearchType::EmployeeId => User::withTrashed()->where('employee_id', $searchValue),
        };

        // An active account comes before a deactivated one with the same
        // email, because PostgreSQL sorts nulls first when descending.
        return $query->orderByDesc('deleted_at')->first();
    }

    /**
     * Determine whether the entry is missing or lacks an affiliation or email address.
     *
     * That usually means someone who left Northwestern, some affiliates, or
     * a student who never matriculated or whose admission was canceled.
     *
     * @param  array<string, mixed>|false|null  $directoryData
     *
     * @phpstan-assert-if-false array<string, mixed> $directoryData
     */
    private function isDirectoryDataInvalid(array|false|null $directoryData): bool
    {
        return ! $directoryData || ! Arr::get($directoryData, 'eduPersonPrimaryAffiliation') || ! Arr::get($directoryData, 'mail');
    }

    /**
     * Resolve a failed lookup, or an entry that is missing or incomplete.
     *
     * An existing SSO account records the failure and keeps the details
     * it has. An incomplete entry also marks its NetID inactive: the
     * account is treated as deprovisioned and keeps its history.
     *
     * A failed lookup doesn't. Directory Search answers false both for
     * a NetID it doesn't know and when it can't be reached, and an
     * outage mustn't cost anyone their account or credentials.
     * EventHub's NetID updates still deactivate accounts.
     *
     * With no account, a failed lookup returns null, which
     * callers treat as an outage: sign-in retries, then
     * shows the 503 page. An incomplete entry throws
     * BadDirectoryEntry for the caller to report.
     *
     * @param  array<string, mixed>|false|null  $directoryData
     *
     * @throws BadDirectoryEntry
     */
    private function resolveInvalidDirectoryEntry(?User $existingUser, string $searchValue, array|false|null $directoryData): ?User
    {
        if ($existingUser instanceof User) {
            if ($existingUser->auth_type === AuthType::SSO) {
                $existingUser->update([
                    ...($directoryData === false ? [] : ['netid_inactive' => true]),
                    'directory_sync_last_failed_at' => now(),
                ]);
            }

            return $existingUser;
        }

        if ($directoryData === false) {
            return null;
        }

        throw new BadDirectoryEntry(
            netId: $directoryData['uid'] ?? $searchValue,
            directoryData: $directoryData ?? []
        );
    }

    /**
     * Update the account from the directory entry and save it.
     *
     * @param  array<string, mixed>  $directoryData
     */
    private function syncAndPersistUser(array $directoryData, DirectorySearchType $searchType): User
    {
        // The NetID is who someone is. A student is stored with their student address, and
        // anyone's address can change, so an email search looks for the NetID first.
        $user = User::firstExistingSsoByNetIdOrNew((string) Arr::get($directoryData, 'uid', ''));

        if (! $user->exists && $searchType === DirectorySearchType::Email) {
            $user = User::firstExistingByEmailOrNewSso($directoryData['mail']);
        }

        $user = ($this->directorySync)($user, $directoryData);

        return ($this->persistUserWithUniqueUsername)($user);
    }

    /**
     * The jobs to run after a user is retrieved.
     *
     * This is where an application adds its own work after every sync. Each
     * job implements ShouldQueue and takes the User in its constructor.
     *
     * @return list<class-string<ShouldQueue>>
     */
    private function postRetrievalJobs(): array
    {
        $jobs = [
            // Add your own post-retrieval jobs here; the docs describe what a job needs.
        ];

        if (config('platform.wildcard_photo_sync')) {
            $jobs[] = DownloadWildcardPhotoJob::class;
        }

        return $jobs;
    }
}
