<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\User\Enums;

use App\Domains\User\Enums\DirectorySearchType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectorySearchType::class)]
final class DirectorySearchTypeTest extends TestCase
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
     * @return \Iterator<string, array{string, DirectorySearchType}>
     */
    public static function validSearchValuesProvider(): \Iterator
    {
        yield 'typical email' => ['example@northwestern.edu', DirectorySearchType::Email];
        yield 'another email format' => ['user@domain.com', DirectorySearchType::Email];
        yield 'complex email' => ['test.email+tag@example.org', DirectorySearchType::Email];
        yield 'employee ID 7 digits' => ['1234567', DirectorySearchType::EmployeeId];
        yield 'netid alphanumeric' => ['abc123', DirectorySearchType::NetId];
        yield 'netid short' => ['abc', DirectorySearchType::NetId];
        yield 'netid long' => ['abcd1234', DirectorySearchType::NetId];
        yield 'netid with hyphens' => ['test-user', DirectorySearchType::NetId];
        yield 'netid longer format' => ['missing-mail-format', DirectorySearchType::NetId];
        yield 'netid alphabetic only' => ['notanemail', DirectorySearchType::NetId];
    }

    /**
     * @return \Iterator<string, array{string, string}>
     */
    public static function invalidSearchValuesProvider(): \Iterator
    {
        yield 'empty string' => ['', 'Search value cannot be empty'];
        yield 'whitespace only' => ['   ', 'Search value cannot be empty'];
    }
}
