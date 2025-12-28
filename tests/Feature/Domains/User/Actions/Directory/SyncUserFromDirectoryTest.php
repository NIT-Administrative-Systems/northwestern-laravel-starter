<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\User\Actions\Directory;

use App\Domains\Auth\Enums\AuthTypeEnum;
use App\Domains\User\Actions\Directory\SyncUserFromDirectory;
use App\Domains\User\Enums\AffiliationEnum;
use App\Domains\User\Models\User;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(SyncUserFromDirectory::class)]
class SyncUserFromDirectoryTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $directoryData
     * @param  array<string, mixed>  $expectedAttributes
     */
    #[DataProvider('directoryDataProvider')]
    public function test_sync_user(array $directoryData, array $expectedAttributes): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'foo']);
        $user = $this->service()($user, $directoryData);

        $actualAttributes = Arr::only($user->getAttributes(), array_keys($expectedAttributes));
        $this->assertEquals($expectedAttributes, $actualAttributes);
    }

    /**
     * @return array<string, mixed>
     */
    public static function directoryDataProvider(): array
    {
        return [
            'student w/ job at NU too' => [self::studentData(), ['first_name' => 'Studie', 'last_name' => 'NU', 'primary_affiliation' => 'student', 'email' => 'StudieNU20XX@u.northwestern.edu']],
            'student, short employeeNumber' => [self::customDirectoryData('student', ['employeeNumber' => '123456', 'nuStudentNumber' => '7654321']),
                ['employee_id' => '7654321', 'hr_employee_id' => '123456']],
            'student, short nuStudentNumber' => [self::customDirectoryData('student', ['nuStudentNumber' => '654321', 'employeeNumber' => '1234567']),
                ['employee_id' => '1234567', 'hr_employee_id' => '1234567']],
            'student, short id numbers' => [self::customDirectoryData('student', ['employeeNumber' => '123456', 'nuStudentNumber' => '654321']),
                ['employee_id' => null, 'hr_employee_id' => '123456']],
            'student, null nuStudentNumber with valid employeeNumber' => [
                self::customDirectoryData('student', ['nuStudentNumber' => null, 'employeeNumber' => '1109225']),
                ['employee_id' => '1109225', 'hr_employee_id' => null],
            ],

            'faculty, with a staff affiliation' => [self::facultyData(), ['primary_affiliation' => 'faculty', 'email' => 'faculty@northwestern.edu']],
            'faculty, short employeeNumber' => [self::customDirectoryData('faculty', ['employeeNumber' => '123456', 'nuStudentNumber' => '7654321']),
                ['employee_id' => '7654321', 'hr_employee_id' => '123456']],
            'faculty, short nuStudentNumber' => [self::customDirectoryData('faculty', ['nuStudentNumber' => '654321', 'employeeNumber' => '1234567']),
                ['employee_id' => '1234567', 'hr_employee_id' => '1234567']],
            'faculty, short id numbers' => [self::customDirectoryData('faculty', ['employeeNumber' => '123456', 'nuStudentNumber' => '654321']),
                ['employee_id' => null, 'hr_employee_id' => '123456']],
            'faculty, null nuStudentNumber with valid employeeNumber' => [
                self::customDirectoryData('faculty', ['nuStudentNumber' => null, 'employeeNumber' => '1109225']),
                ['employee_id' => '1109225', 'hr_employee_id' => null],
            ],

            'staff, no other affiliation' => [self::staffData(), ['primary_affiliation' => 'staff', 'email' => 'staff@northwestern.edu']],
            'staff, short employeeNumber' => [self::customDirectoryData('staff', ['employeeNumber' => '123456', 'nuStudentNumber' => '7654321']),
                ['employee_id' => '7654321', 'hr_employee_id' => '123456']],
            'staff, short nuStudentNumber' => [self::customDirectoryData('staff', ['nuStudentNumber' => '654321', 'employeeNumber' => '1234567']),
                ['employee_id' => '1234567', 'hr_employee_id' => '1234567']],
            'staff, short id numbers' => [self::customDirectoryData('staff', ['employeeNumber' => '123456', 'nuStudentNumber' => '654321']),
                ['employee_id' => null, 'hr_employee_id' => '123456']],
            'staff, null nuStudentNumber with valid employeeNumber' => [
                self::customDirectoryData('staff', ['nuStudentNumber' => null, 'employeeNumber' => '1109225']),
                ['employee_id' => '1109225', 'hr_employee_id' => null],
            ],

            'contractor w/ affiliate netID' => [self::contractorData(), ['primary_affiliation' => 'affiliate', 'email' => 'contractor@northwestern.edu']],
            'contractor, short employeeNumber' => [self::customDirectoryData('contractor', ['employeeNumber' => '123456', 'nuStudentNumber' => '7654321']),
                ['employee_id' => '7654321', 'hr_employee_id' => '123456']],
            'contractor, short nuStudentNumber' => [self::customDirectoryData('contractor', ['nuStudentNumber' => '654321', 'employeeNumber' => '1234567']),
                ['employee_id' => '1234567', 'hr_employee_id' => '1234567']],
            'contractor, short id numbers' => [self::customDirectoryData('contractor', ['employeeNumber' => '123456', 'nuStudentNumber' => '654321']),
                ['employee_id' => null, 'hr_employee_id' => '123456']],
            'contractor, null nuStudentNumber with valid employeeNumber' => [
                self::customDirectoryData('contractor', ['nuStudentNumber' => null, 'employeeNumber' => '1109225']),
                ['employee_id' => '1109225', 'hr_employee_id' => null],
            ],
        ];
    }

    public function test_assigns_username_from_directory_when_blank(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO]);
        $directoryData = self::studentData();
        $directoryData['uid'] = 'new_user';

        $result = $this->service()($user, $directoryData);

        $this->assertEquals('new_user', $result->username);
    }

    public function test_handles_missing_uid_when_user_has_no_username(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO]);
        $directoryData = self::studentData();
        unset($directoryData['uid']);

        $result = $this->service()($user, $directoryData);

        $this->assertNull($result->username);
    }

    public function test_handles_invalid_primary_affiliation(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = self::studentData();
        $directoryData['eduPersonPrimaryAffiliation'] = 'invalid_affiliation';

        $result = $this->service()($user, $directoryData);

        $this->assertEquals(AffiliationEnum::OTHER, $result->primary_affiliation);
    }

    public function test_trims_whitespace_from_values(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = [
            'eduPersonPrimaryAffiliation' => 'student',
            'givenName' => ['  John  '],
            'sn' => ['  Doe  '],
            'mail' => '  john.doe@northwestern.edu  ',
        ];

        $result = $this->service()($user, $directoryData);

        $this->assertEquals('John', $result->first_name);
        $this->assertEquals('Doe', $result->last_name);
        $this->assertEquals('john.doe@northwestern.edu', $result->email);
    }

    public function test_handles_empty_array_values(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = [
            'eduPersonPrimaryAffiliation' => 'student',
            'givenName' => [],
            'sn' => ['Doe'],
            'nuAllDepartmentName' => ['', '  ', null, 'Valid Department'],
        ];

        $result = $this->service()($user, $directoryData);

        $this->assertNull($result->first_name);
        $this->assertEquals('Doe', $result->last_name);
        $this->assertEquals(['Valid Department'], $result->departments);
    }

    public function test_filters_short_employee_ids(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = [
            'eduPersonPrimaryAffiliation' => 'staff',
            'employeeNumber' => '123456', // Too short
            'nuStudentNumber' => '1234567', // Valid length
        ];

        $result = $this->service()($user, $directoryData);

        $this->assertEquals('1234567', $result->employee_id);
        $this->assertEquals('123456', $result->hr_employee_id); // Still set as hr_employee_id
    }

    public function test_sets_hr_employee_id_when_different_from_student_number(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = [
            'eduPersonPrimaryAffiliation' => 'student',
            'employeeNumber' => '1234567',
            'nuStudentNumber' => '7654321',
        ];

        $result = $this->service()($user, $directoryData);

        $this->assertEquals('7654321', $result->employee_id); // Student number has priority for students
        $this->assertEquals('1234567', $result->hr_employee_id);
    }

    public function test_does_not_set_hr_employee_id_when_same_as_student_number(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = [
            'eduPersonPrimaryAffiliation' => 'student',
            'employeeNumber' => '1234567',
            'nuStudentNumber' => '1234567',
        ];

        $result = $this->service()($user, $directoryData);

        $this->assertEquals('1234567', $result->employee_id);
        $this->assertNull($result->hr_employee_id);
    }

    public function test_student_data_takes_priority_for_students(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = [
            'eduPersonPrimaryAffiliation' => 'student',
            'givenName' => ['Generic Name'],
            'nuStudentGivenName' => 'Student Name',
            'sn' => ['Generic Last'],
            'nuStudentSn' => 'Student Last',
            'mail' => 'generic@northwestern.edu',
            'nuStudentEmail' => 'student@u.northwestern.edu',
        ];

        $result = $this->service()($user, $directoryData);

        $this->assertEquals('Student Name', $result->first_name);
        $this->assertEquals('Student Last', $result->last_name);
        $this->assertEquals('student@u.northwestern.edu', $result->email);
    }

    public function test_removes_duplicate_values_in_arrays(): void
    {
        $user = new User(['auth_type' => AuthTypeEnum::SSO, 'username' => 'test']);
        $directoryData = [
            'eduPersonPrimaryAffiliation' => 'staff',
            'nuAllDepartmentName' => ['IT', 'HR', 'IT', 'Finance', 'HR'],
        ];

        $result = $this->service()($user, $directoryData);

        $this->assertCount(3, $result->departments);
        $this->assertContains('IT', $result->departments);
        $this->assertContains('HR', $result->departments);
        $this->assertContains('Finance', $result->departments);
    }

    protected function service(): SyncUserFromDirectory
    {
        return resolve(SyncUserFromDirectory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function contractorData(): array
    {
        return [
            'uid' => 'kke5407',
            'mail' => 'contractor@northwestern.edu',
            'nuStudentEmail' => '',
            'nuAllGivenName' => 'Expert',
            'nuAllSn' => 'Consultant',
            'employeeNumber' => '',
            'nuStudentNumber' => '',
            'nuAllSchoolAffiliations' => [
                'srv:nuadsad',
                'nuAffiliate',
                'flg:prilikestaf',
            ],
            'eduPersonPrimaryAffiliation' => 'affiliate',
            'displayName' => ['Expert Consultant'],
            'givenName' => ['Expert'],
            'sn' => ['Consultant'],
            'eduPersonAffiliation' => ['affiliate'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function studentData(): array
    {
        return [
            'nuAllSchoolAffiliations' => ['student'],
            'eduPersonPrimaryAffiliation' => 'student',
            'givenName' => [
                'Studie',
            ],
            'sn' => [
                'NU',
            ],
            'uid' => 'example',
            'mail' => 'student-worker@northwestern.edu',
            'nuStudentEmail' => 'StudieNU20XX@u.northwestern.edu',
            'telephoneNumber' => '',
            'address' => '',
            'eduPersonAffiliation' => [
                'employee',
                'member',
                'student',
            ],
            'nuLegalSn' => '',
            'nuLegalGivenName' => '',
            'employeeNumber' => '9999999',
            'nuStudentNumber' => '1111111',
            'nuAllStudentCurrentPhone' => [
                '+1 573 111 1111',
            ],
            'nuAllStudentCurrentAddress' => [
                '1801 Maple Ave$Apt 401$Chicago, IL 60640$USA',
            ],
            'nuStudentGivenName' => 'Studie',
            'nuStudentSn' => 'NU',
            'nuStudentLegalGivenName' => 'Student',
            'nuStudentLegalSn' => 'NU',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function facultyData(): array
    {
        return [
            'nuAllSchoolAffiliations' => ['faculty', 'staff'],
            'eduPersonPrimaryAffiliation' => 'faculty',
            'givenName' => [
                'Faculty',
            ],
            'sn' => [
                'Member',
            ],
            'uid' => 'example',
            'mail' => 'faculty@northwestern.edu',
            'nuStudentEmail' => 'faculty@u.northwestern.edu',
            'telephoneNumber' => '+1 847 111 1111',
            'address' => '',
            'eduPersonAffiliation' => [
                'employee',
                'faculty',
                'member',
                'staff',
            ],
            'nuLegalSn' => 'Member',
            'nuLegalGivenName' => 'Faculty',
            'employeeNumber' => '1111111',
            'nuStudentNumber' => '',
            'nuAllStudentCurrentPhone' => [],
            'nuAllStudentCurrentAddress' => [],
            'nuStudentGivenName' => '',
            'nuStudentSn' => '',
            'nuStudentLegalGivenName' => '',
            'nuStudentLegalSn' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function staffData(): array
    {
        return [
            'nuMiddleName' => '',
            'uid' => 'example',
            'mail' => 'staff@northwestern.edu',
            'nuStudentEmail' => '',
            'nuAllGivenName' => 'Pure',
            'nuAllSn' => 'Staffer',
            'employeeNumber' => '1094367',
            'nuStudentNumber' => '',
            'nuBarCode' => '80000500705697',
            'nuAllSchoolAffiliations' => ['staff'],
            'eduPersonPrimaryAffiliation' => 'staff',
            'displayName' => [
                'Pure Staffer',
            ],
            'givenName' => [
                'Pure',
            ],
            'sn' => [
                'Staffer',
            ],
            'eduPersonNickname' => [],
            'nuAllTitle' => [
                'Developer Senior',
                'Developer Elder',
            ],
            'nuAllDepartmentTitle' => [
                'Developer Sr',
            ],
            'ou' => 'IT Admin Systems Application Development & Operations',
            'telephoneNumber' => '847 4915281',
            'nuTelephoneNumber2' => '',
            'nuTelephoneNumber3' => '',
            'mobile' => '',
            'facsimileTelephoneNumber' => '',
            'nuCurriculumOnly' => [],
            'nuOtherAddress' => '',
            'nuOtherDepartment' => '',
            'nuOtherPhone' => '',
            'nuOtherTitle' => '',
            'eduPersonAffiliation' => [
                'employee',
                'member',
                'staff',
            ],
            'nuLegalSn' => 'Staffer',
            'nuAllLegalName' => [
                'Pure I Staffer',
            ],
            'nuLegalGivenName' => 'Pure',
            'nuLegalMiddleName' => 'I',
            'nuPosition1' => 'Developer Senior$$IT Admin Systems Application Development & Operations$$1800 Sherman Ave$Suite 600$$EV',
            'nuPosition2' => '',
            'nuPosition3' => '',
            'nuPosition4' => '',
            'nuPosition5' => '',
            'nuPosition6' => '',
            'nuPosition7' => '',
            'nuPosition8' => '',
            'nuPosition9' => '',
            'nuPosition10' => '',
            'postalAddress' => [
                '1800 Sherman Ave$Suite 600$EV',
            ],
            'nuAllDisplayName' => 'Pure Staffer',
            'nuAllDepartmentName' => [
                'IT Admin Systems Application Development & Operations',
                'Council of Elders',
            ],
            'departmentNumber' => '792700',
            'nuAllProName' => [
                'Nick Evans',
            ],
            'nuProGivenName' => 'Pure',
            'nuProSn' => 'Staffer',
            'nuNetidStatus' => 'active',
            'nuCareer' => [],
            'nuAllStudentCurrentPhone' => [],
            'nuAllStudentCurrentAddress' => [],
            'nuAllStudentName' => [],
            'nuStudentGivenName' => '',
            'nuStudentMiddleName' => '',
            'nuStudentSn' => '',
            'nuStudentLegalName' => '',
            'nuStudentLegalGivenName' => '',
            'nuStudentLegalMiddleName' => '',
            'nuStudentLegalSn' => '',
            'nuStudentPermanentAddress' => '',
            'nuStudentPermanentPhone' => '',
            'eduPersonOrcid' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $modifications
     * @return array<string, mixed>
     */
    public static function customDirectoryData(string $type, array $modifications): array
    {
        $data = match ($type) {
            'student' => self::studentData(),
            'staff' => self::staffData(),
            'faculty' => self::facultyData(),
            'contractor' => self::contractorData(),
            default => throw new InvalidArgumentException("Invalid custom directory data type: {$type}"),
        };

        foreach ($modifications as $key => $value) {
            if (array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
