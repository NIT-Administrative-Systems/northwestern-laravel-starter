<?php

declare(strict_types=1);

namespace App\Domains\User\Actions\Directory;

use App\Domains\User\Enums\Affiliation;
use App\Domains\User\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SyncUserFromDirectory
{
    /**
     * Update the user from their directory entry, without saving it.
     *
     * @param  array<string, mixed>  $directoryData
     */
    public function __invoke(User $user, array $directoryData): User
    {
        // A user found by email may not have a username yet.
        if (blank($user->username)) {
            $user->username = Arr::get($directoryData, 'uid');
        }

        $eduPrimaryAffiliation = Affiliation::tryFrom($directoryData['eduPersonPrimaryAffiliation'] ?? '');
        $user->primary_affiliation = $eduPrimaryAffiliation ?? Affiliation::Other;

        $user = $this->syncDemographics($user, $directoryData);

        $user->netid_inactive = false;
        $user->last_directory_sync_at = Carbon::now();

        return $user;
    }

    /**
     * Fill in the user's name, contact details, IDs, departments and job titles.
     *
     * Several directory fields can hold the same detail, such as
     * a student's email and the general one. Each detail takes
     * the first of its fields, in order, that has a value.
     *
     * @param  array<string, mixed>  $directoryData
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

        // A student's own fields come before the general ones.
        if ($user->primary_affiliation === Affiliation::Student) {
            array_unshift($firstNameKeys, 'nuStudentGivenName');
            array_unshift($lastNameKeys, 'nuStudentSn');
            array_unshift($employeeIdKeys, 'nuStudentNumber');
            array_unshift($emailKeys, 'nuStudentEmail');
            array_unshift($phoneKeys, 'nuAllStudentCurrentPhone');

            // A student's job title reads like Temporary Student, which isn't useful or kind.
            $jobTitleKeys = [];
        }

        $nuStudentNumber = $this->findValue($directoryData, ['nuStudentNumber']);
        $employeeNumber = $this->findValue($directoryData, ['employeeNumber']);

        // An ID shorter than seven characters isn't a real one.
        if ($nuStudentNumber !== null && strlen($nuStudentNumber) < 7) {
            $employeeIdKeys = array_values(array_diff($employeeIdKeys, ['nuStudentNumber']));
        }
        if ($employeeNumber !== null && strlen($employeeNumber) < 7) {
            $employeeIdKeys = array_values(array_diff($employeeIdKeys, ['employeeNumber']));
        }

        // Keep the myHR ID only when it differs from the student ID; clear it otherwise.
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

        // The directory has no timezone. New users get the default, then choose their own.
        if (! $user->exists) {
            $user->timezone = config('platform.default_user_timezone');
        }

        return $user;
    }

    /**
     * Get the first non-empty value among the keys, in order.
     *
     * Some fields hold a list, such as givenName, and give their first non-empty item.
     *
     * @param  array<string, mixed>  $directoryFields
     * @param  list<string>  $keys
     */
    private function findValue(array $directoryFields, array $keys): ?string
    {
        foreach ($keys as $key) {
            foreach (Arr::wrap($directoryFields[$key] ?? null) as $item) {
                $value = trim((string) $item);

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Get every non-empty value of the keys, trimmed and without duplicates.
     *
     * @param  array<string, mixed>  $directoryFields
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function findAll(array $directoryFields, array $keys): array
    {
        return array_values(collect($keys)
            ->map(fn (string $key): mixed => Arr::get($directoryFields, $key))
            ->flatten()
            ->map(fn (mixed $value): string => trim((string) $value))
            ->reject(fn (string $value): bool => $value === '')
            ->unique()
            ->all());
    }
}
