<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\User\Enums;

use App\Domains\User\Enums\DirectorySearchType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectorySearchType::class)]
class DirectorySearchTypeTest extends TestCase
{
    #[DataProvider('validSearchValuesProvider')]
    public function test_it_detects_search_type(string $searchValue, DirectorySearchType $expectedType): void
    {
        $result = DirectorySearchType::fromSearchValue($searchValue);

        $this->assertSame($expectedType, $result);
    }

    #[DataProvider('invalidSearchValuesProvider')]
    public function test_invalid_search_values_throw_exception(string $searchValue, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        DirectorySearchType::fromSearchValue($searchValue);
    }

    public function test_email_validation_is_strict(): void
    {
        $validEmails = [
            'user@domain.com',
            'test@northwestern.edu',
            'complex.email+tag@example.org',
        ];

        foreach ($validEmails as $email) {
            $result = DirectorySearchType::fromSearchValue($email);
            $this->assertSame(DirectorySearchType::Email, $result);
        }
    }

    public function test_employee_id_validation(): void
    {
        // Any numeric string is treated as employee ID
        $validIds = ['1', '123', '123456', '1234567', '12345678'];
        foreach ($validIds as $id) {
            $result = DirectorySearchType::fromSearchValue($id);
            $this->assertSame(DirectorySearchType::EmployeeId, $result);
        }
    }

    public function test_netid_validation(): void
    {
        $validNetIds = ['abc', 'abc123', 'user1', 'test', 'a1b2c3d4', 'test-user', 'existing-no-email', 'user!@#'];
        foreach ($validNetIds as $netId) {
            $result = DirectorySearchType::fromSearchValue($netId);
            $this->assertSame(DirectorySearchType::NetId, $result);
        }
    }

    public function test_whitespace_is_trimmed(): void
    {
        $result = DirectorySearchType::fromSearchValue('  abc123  ');
        $this->assertSame(DirectorySearchType::NetId, $result);

        $result = DirectorySearchType::fromSearchValue('  user@example.com  ');
        $this->assertSame(DirectorySearchType::Email, $result);

        $result = DirectorySearchType::fromSearchValue('  1234567  ');
        $this->assertSame(DirectorySearchType::EmployeeId, $result);
    }

    /**
     * @return array<string, array{string, DirectorySearchType}>
     */
    public static function validSearchValuesProvider(): array
    {
        return [
            'typical email' => ['example@northwestern.edu', DirectorySearchType::Email],
            'another email format' => ['user@domain.com', DirectorySearchType::Email],
            'complex email' => ['test.email+tag@example.org', DirectorySearchType::Email],
            'employee ID 7 digits' => ['1234567', DirectorySearchType::EmployeeId],
            'netid alphanumeric' => ['abc123', DirectorySearchType::NetId],
            'netid short' => ['abc', DirectorySearchType::NetId],
            'netid long' => ['abcd1234', DirectorySearchType::NetId],
            'netid with hyphens' => ['test-user', DirectorySearchType::NetId],
            'netid longer format' => ['missing-mail-format', DirectorySearchType::NetId],
            'netid alphabetic only' => ['notanemail', DirectorySearchType::NetId],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidSearchValuesProvider(): array
    {
        return [
            'empty string' => ['', 'Search value cannot be empty'],
            'whitespace only' => ['   ', 'Search value cannot be empty'],
        ];
    }
}
