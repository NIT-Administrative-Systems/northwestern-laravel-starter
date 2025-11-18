<?php

declare(strict_types=1);

namespace App\Domains\User\Actions;

use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SyncUserFromDirectory
{
    /**
     * Updates a {@see User} model with appropriate directory data for their affiliations.
     *
     * This model HAS NOT been saved yet; the calling code will need to call {@see Model::save()}.
     *
     * @param  array<string, mixed>  $directoryData
     */
    public function __invoke(User $user, array $directoryData): User
    {
        // For create-by-email, this might not be set yet - assign it from the directory data.
        if (blank($user->username)) {
            $user->username = Arr::get($directoryData, 'uid');
        }

        $eduPrimaryAffiliation = AffiliationEnum::tryFrom($directoryData['eduPersonPrimaryAffiliation'] ?? '');
        $user->primary_affiliation = $eduPrimaryAffiliation ?? AffiliationEnum::OTHER;

        $user = $this->syncDemographics($user, $directoryData);

        $user->netid_inactive = false;
        $user->last_directory_sync_at = Carbon::now();

        return $user;
    }

    /**
     * Synchronizes demographic data from the Northwestern Directory into the user model with field priority logic.
     *
     * This method maps LDAP directory fields to user model attributes using a priority-based
     * system. When multiple directory fields could provide the same data (e.g., student email
     * vs. general email), it searches them in priority order and uses the first non-empty value.
     *
     * @param  User  $user  The user model to update (not yet saved)
     * @param  array<string, mixed>  $directoryData  Raw LDAP directory data
     * @return User The updated user model (still needs to be saved by caller)
     *
     * @see findValue() for the priority search logic
     * @see findAll() for multi-value field extraction
     */
    protected function syncDemographics(User $user, array $directoryData): User
    {
        $firstNameKeys = ['givenName'];
        $lastNameKeys = ['sn'];
        $employeeIdKeys = ['nuStudentNumber', 'employeeNumber'];
        $emailKeys = ['mail'];
        $phoneKeys = ['telephoneNumber'];
        $departmentKeys = ['nuAllDepartmentName'];
        $jobTitleKeys = ['nuAllTitle'];

        // Student data fields should have priority over less-specific fields when this is a student
        if ($user->primary_affiliation === AffiliationEnum::STUDENT) {
            array_unshift($firstNameKeys, 'nuStudentGivenName');
            array_unshift($lastNameKeys, 'nuStudentSn');
            array_unshift($employeeIdKeys, 'nuStudentNumber');
            array_unshift($emailKeys, 'nuStudentEmail');
            array_unshift($phoneKeys, 'nuAllStudentCurrentPhone');

            // Job title for a student looks like 'Temporary Student'.
            // That's (a) not useful and (b) probably rude, so don't pick it up.
            $jobTitleKeys = [];
        }

        $nuStudentNumber = $this->findValue($directoryData, ['nuStudentNumber']);
        $employeeNumber = $this->findValue($directoryData, ['employeeNumber']);

        // If either of the emplid fields are less than 7 characters, remove them from the array because they're trash
        if ($nuStudentNumber !== null && strlen($nuStudentNumber) < 7) {
            $employeeIdKeys = array_values(array_diff($employeeIdKeys, ['nuStudentNumber']));
        }
        if ($employeeNumber !== null && strlen($employeeNumber) < 7) {
            $employeeIdKeys = array_values(array_diff($employeeIdKeys, ['employeeNumber']));
        }

        // Set hr_employee_id to employeeNumber only if both IDs exist and are different
        // Otherwise, explicitly set it to null to ensure it's synced from directory data
        $user->hr_employee_id = ($employeeNumber !== null && $nuStudentNumber !== null && $employeeNumber !== $nuStudentNumber)
            ? $employeeNumber
            : null;

        $user->first_name = $this->findValue($directoryData, $firstNameKeys);
        $user->last_name = $this->findValue($directoryData, $lastNameKeys);
        $user->phone = $this->findValue($directoryData, $phoneKeys);
        $user->email = $this->findValue($directoryData, $emailKeys);
        $user->employee_id = $this->findValue($directoryData, $employeeIdKeys);
        $user->departments = $this->findAll($directoryData, $departmentKeys);
        $user->job_titles = $this->findAll($directoryData, $jobTitleKeys);

        $user->timezone = 'America/Chicago';

        return $user;
    }

    /**
     * Check for the presence of multiple potential keys & return the first found.
     *
     * Some of the DirectorySearch results are ['a key'][0].
     * This helper pops the first array key out, if it looks like that.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function findValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                // Find the first non-empty value in the array
                foreach ($value as $item) {
                    $trimmed = trim((string) $item);
                    if (filled($trimmed)) {
                        return $trimmed;
                    }
                }

                continue;
            }

            $trimmed = trim((string) $value);
            if (filled($trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Retrieves all non-empty values for the specified keys, flattened into a single array.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function findAll(array $data, array $keys): array
    {
        $values = [];

        foreach ($keys as $key) {
            $value = Arr::get($data, $key);
            if ($value !== null) {
                $values[] = $value;
            }
        }

        $result = collect($values)
            ->flatten()
            ->reject(fn (mixed $value): bool => blank(trim((string) $value)))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->unique()
            ->values()
            ->all();

        return array_values($result);
    }
}
